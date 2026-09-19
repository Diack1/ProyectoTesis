#pragma once
// Copy to config.h. Never commit network passwords or the sensor token.
// Start in serial diagnostics. Do not enable GPIOs until the board and wiring are verified.
constexpr bool HARDWARE_CONFIRMED = false;
constexpr bool SEND_TO_SERVER = false;
// ESP32 DEVKIT V1 TYPE-C photographed by the user: use the printed D18/D19 labels.
// ECHO must pass through suitable voltage adaptation; never connect a 5 V signal directly.
constexpr int TRIG_PIN = 18;
constexpr int ECHO_PIN = 19;
constexpr unsigned long TRIGGER_US = 20;
constexpr unsigned long ECHO_TIMEOUT_US = 35000;
constexpr unsigned long SAMPLE_INTERVAL_MS = 1000;
constexpr const char* WIFI_SSID = "";
constexpr const char* WIFI_PASSWORD = "";
// Use the PC's LAN IP, not localhost, when connecting from the ESP32.
constexpr const char* SENSOR_URL = ""; // https://host/api/iot/sensores/CODE/lecturas
constexpr const char* SENSOR_TOKEN = "";
constexpr bool ALLOW_PRIVATE_LAN_HTTP = false;
// PEM root CA for the HTTPS server. Do not replace verification with setInsecure().
constexpr const char* ROOT_CA = "";
