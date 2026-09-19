import {test} from 'node:test';
import assert from 'node:assert/strict';
import {PlateTracker} from '../public/js/plate-tracker.js';
const plate = (text = 'ABC123') => ({text, detection_confidence: .95, ocr_confidence: .95});

test('requires two distinct consecutive frames and suppresses repeated visibility', () => {
    const t = new PlateTracker();
    assert.equal(t.update([plate(), plate()], 1000).length, 0);
    assert.equal(t.update([plate()], 4000).length, 1);
    assert.equal(t.update([plate()], 7000).length, 0);
    for (let now = 10000; now < 100000; now += 3000) assert.equal(t.update([plate()], now).length, 0);
});
test('weak or interrupted reads cannot trigger a stable event', () => {
    const t = new PlateTracker();
    t.update([plate()], 1000);
    t.update([{...plate(), ocr_confidence: .4}], 4000);
    assert.equal(t.update([plate()], 7000).length, 0);
    assert.equal(t.update([plate()], 20000).length, 0);
    assert.equal(t.update([plate()], 23000).length, 1);
});
test('rearms only after absence and cooldown; tracks different vehicles separately', () => {
    const t = new PlateTracker();
    t.update([plate()], 1000); t.update([plate()], 4000);
    t.update([], 25000);
    t.update([plate()], 28000);
    assert.equal(t.update([plate()], 31000).length, 0);
    t.update([], 64000); t.update([plate(), plate('XYZ789')], 67000);
    assert.equal(t.update([plate(), plate('XYZ789')], 70000).length, 2);
});
