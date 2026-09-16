import { describe, expect, it } from 'vitest';
import { bulkFlowVuePackage } from '../src/index';

describe('@bulkflow/vue', () => {
  it('exposes its stable package identifier', () => {
    expect(bulkFlowVuePackage).toBe('@bulkflow/vue');
  });
});
