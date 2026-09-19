/*
 * Parke'o: Arduino IDE sketch for a JSN-SR04T configured in TRIG/ECHO mode.
 * Requires verification of the specific board, voltage adaptation and sensor mode.
 * A02 RS485 protocol is intentionally not guessed; it needs a separate verified driver.
 * No hardware or Wi-Fi action occurs with the example configuration.
 */
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <esp_random.h>
#include <time.h>
#include "config.h"

String pendingPayload;
unsigned long lastSample = 0;
unsigned long lastAttempt = 0;
unsigned long lastReconnect = 0;
unsigned long payloadCreated = 0;
bool hardwareReady = false;
bool networkReady = false;

bool privateLanHttp(const String& url) {
  if (!ALLOW_PRIVATE_LAN_HTTP || !url.startsWith("http://")) return false;
  String host = url.substring(7);
  int slash = host.indexOf('/');
  if (slash >= 0) host = host.substring(0, slash);
  int colon = host.indexOf(':');
  if (colon >= 0) host = host.substring(0, colon);
  IPAddress ip;
  if (!ip.fromString(host)) return false;
  return ip[0] == 10 || (ip[0] == 172 && ip[1] >= 16 && ip[1] <= 31)
      || (ip[0] == 192 && ip[1] == 168);
}

String eventUuid() {
  uint8_t b[16];
  esp_fill_random(b, sizeof(b));
  b[6] = (b[6] & 0x0f) | 0x40;
  b[8] = (b[8] & 0x3f) | 0x80;
  char out[37];
  snprintf(out, sizeof(out), "%02x%02x%02x%02x-%02x%02x-%02x%02x-%02x%02x-%02x%02x%02x%02x%02x%02x",
      b[0],b[1],b[2],b[3],b[4],b[5],b[6],b[7],b[8],b[9],b[10],b[11],b[12],b[13],b[14],b[15]);
  return String(out);
}

bool readDistance(float& cm) {
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(3);
  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(TRIGGER_US);
  digitalWrite(TRIG_PIN, LOW);
  const unsigned long duration = pulseIn(ECHO_PIN, HIGH, ECHO_TIMEOUT_US);
  if (duration == 0) return false;
  // Approximate speed of sound; final calibration belongs to the actual installation.
  cm = duration * 0.0343f / 2.0f;
  return cm > 0 && cm <= 10000;
}

int sendPayload(const String& payload) {
  HTTPClient http;
  WiFiClient plain;
  WiFiClientSecure secure;
  const String url(SENSOR_URL);
  bool started = false;
  if (url.startsWith("https://") && strlen(ROOT_CA) > 0) {
    if (time(nullptr) < 1700000000) return -1; // Certificate checks need a valid clock.
    secure.setCACert(ROOT_CA);
    started = http.begin(secure, url);
  } else if (privateLanHttp(url)) {
    started = http.begin(plain, url);
  }
  if (!started) return -1;
  http.setConnectTimeout(2000);
  http.setTimeout(2000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("Authorization", String("Bearer ") + SENSOR_TOKEN);
  const int status = http.POST(payload);
  // Never print the token or Wi-Fi password.
  Serial.printf("Respuesta del servidor: %d\n", status);
  http.end();
  return status;
}

void setup() {
  Serial.begin(115200);
  hardwareReady = HARDWARE_CONFIRMED && TRIG_PIN >= 0 && ECHO_PIN >= 0 && TRIG_PIN != ECHO_PIN;
  if (!hardwareReady) {
    Serial.println("Pendiente: confirmar placa, modo TRIG/ECHO, niveles eléctricos y pines en config.h.");
    return;
  }
  pinMode(TRIG_PIN, OUTPUT);
  digitalWrite(TRIG_PIN, LOW);
  pinMode(ECHO_PIN, INPUT);
  networkReady = SEND_TO_SERVER && strlen(WIFI_SSID) > 0 && strlen(SENSOR_TOKEN) > 0
      && ((String(SENSOR_URL).startsWith("https://") && strlen(ROOT_CA) > 0) || privateLanHttp(String(SENSOR_URL)));
  if (networkReady) {
    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    configTime(0, 0, "pool.ntp.org", "time.nist.gov");
  } else {
    Serial.println("Diagnóstico serial: envío a la web desactivado.");
  }
}

void loop() {
  if (!hardwareReady) { delay(100); return; }
  const unsigned long tick = millis();
  if (networkReady && WiFi.status() != WL_CONNECTED && tick - lastReconnect >= 10000) {
    lastReconnect = tick;
    WiFi.reconnect();
  }
  // No offline backlog: old distances must never start a new parking stay.
  if (pendingPayload.length() && tick - payloadCreated > 5000) pendingPayload = "";
  if (!pendingPayload.length() && tick - lastSample >= SAMPLE_INTERVAL_MS) {
    lastSample = tick;
    float cm = 0;
    const bool valid = readDistance(cm);
    Serial.println(valid ? String("Distancia: ") + String(cm, 2) + " cm" : "Sin eco válido");
    if (networkReady && WiFi.status() == WL_CONNECTED) {
      pendingPayload = String("{\"evento_id\":\"") + eventUuid() + "\",\"distancia_cm\":"
          + (valid ? String(cm, 2) : "null") + "}";
      payloadCreated = millis();
    }
  }
  if (networkReady && pendingPayload.length() && WiFi.status() == WL_CONNECTED && tick - lastAttempt >= 1000) {
    lastAttempt = tick;
    const int status = sendPayload(pendingPayload);
    if ((status >= 200 && status < 300) || (status >= 400 && status < 500 && status != 429)) pendingPayload = "";
    if (status == 401 || status == 403) {
      networkReady = false;
      Serial.println("Revisar la credencial antes de reiniciar el envío.");
    }
  }
  delay(10);
}
