// Temporal agreement for UI notifications only. Never authorizes a ticket/payment.
export class PlateTracker {
    constructor() { this.states = new Map(); this.cooldowns = new Map(); }
    reset() { this.states.clear(); this.cooldowns.clear(); }
    update(candidates, now) {
        const valid = new Map();
        for (const c of candidates) {
            if (/^[A-Z0-9]{5,10}$/.test(c.text) && c.detection_confidence >= 0.8 && c.ocr_confidence >= 0.85) valid.set(c.text, c);
        }
        for (const [plate, state] of this.states) {
            if (!valid.has(plate)) state.count = 0;
            if (now - state.seen > 15000) this.states.delete(plate);
        }
        const events = [];
        for (const [plate, candidate] of valid) {
            const previous = this.states.get(plate);
            const state = previous || {count: 0, seen: now, notified: false};
            state.count = now - state.seen <= 10000 ? state.count + 1 : 1;
            state.seen = now;
            if (!state.notified && state.count >= 2 && now >= (this.cooldowns.get(plate) || 0)) {
                state.notified = true;
                this.cooldowns.set(plate, now + 60000);
                events.push(candidate);
            }
            this.states.set(plate, state);
        }
        for (const [plate, until] of this.cooldowns) if (now > until + 60000) this.cooldowns.delete(plate);
        return events;
    }
}
