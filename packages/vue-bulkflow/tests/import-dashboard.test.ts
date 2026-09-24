import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ImportDashboard from '../src/components/ImportDashboard.vue';

describe('ImportDashboard', () => {
  it('renders the current state and progress for every import run', () => {
    const wrapper = mount(ImportDashboard, {
      props: { runs: [{ id: 'run-1', state: 'processing', processedRows: 20, totalRows: 100 }] },
    });

    expect(wrapper.text()).toContain('processing');
    expect(wrapper.text()).toContain('20 / 100');
  });

  it('emits the selected status, page, and run from dashboard controls', async () => {
    const wrapper = mount(ImportDashboard, {
      props: {
        runs: [{ id: 'run-1', state: 'processing', processedRows: 20, totalRows: 100 }],
        currentPage: 2,
        lastPage: 3,
        totalRuns: 3,
      },
    });

    await wrapper.get('[aria-label="Import status"]').setValue('processing');
    await wrapper.get('[aria-label="Next import page"]').trigger('click');
    await wrapper.get('[aria-label="Select import run-1"]').trigger('click');

    expect(wrapper.emitted('filter')).toEqual([['processing']]);
    expect(wrapper.emitted('page-change')).toEqual([[3]]);
    expect(wrapper.emitted('select')).toEqual([['run-1']]);
  });
});
