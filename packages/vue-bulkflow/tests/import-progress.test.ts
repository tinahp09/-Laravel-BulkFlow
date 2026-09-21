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

  it('renders zero percent while a queued import has no total row count yet', () => {
    const wrapper = mount(ImportProgress, {
      props: { processedRows: Number.NaN, totalRows: Number.NaN, successfulRows: 0, failedRows: 0 },
    });

    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('0');
    expect(wrapper.get('[role="progressbar"]').text()).toBe('0%');
  });

  it('updates its percentage when queued progress receives row totals', async () => {
    const wrapper = mount(ImportProgress, {
      props: { processedRows: 0, totalRows: 0, successfulRows: 0, failedRows: 0 },
    });

    await wrapper.setProps({ processedRows: 1, totalRows: 1, successfulRows: 1 });

    expect(wrapper.get('[role="progressbar"]').text()).toBe('100%');
    expect(wrapper.text()).toContain('1 / 1 rows');
  });
});
