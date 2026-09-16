import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ImportProgress from '../src/components/ImportProgress.vue';

describe('ImportProgress', () => {
  it('renders an accessible percentage and row summary', () => {
    const wrapper = mount(ImportProgress, {
      props: { processedRows: 40, totalRows: 100, successfulRows: 37, failedRows: 3 },
    });

    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('40');
    expect(wrapper.text()).toContain('40%');
    expect(wrapper.text()).toContain('37 successful');
    expect(wrapper.text()).toContain('3 failed');
  });
});
