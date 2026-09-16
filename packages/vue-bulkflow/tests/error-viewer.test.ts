import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ErrorViewer from '../src/components/ErrorViewer.vue';

describe('ErrorViewer', () => {
  it('emits the selected failure-status filter', async () => {
    const wrapper = mount(ErrorViewer, {
      props: {
        runId: 'run-1',
        failures: [],
      },
    });

    await wrapper.get('select[aria-label="Failure status"]').setValue('resolved');

    expect(wrapper.emitted('filter')).toEqual([['resolved']]);
  });

  it('shows failures and emits selected ids for the current run', async () => {
    const wrapper = mount(ErrorViewer, {
      props: {
        runId: 'run-1',
        failures: [{ id: 'failure-1', rowNumber: 7, type: 'validation', errors: { email: ['Invalid'] }, payload: { email: 'bad' } }],
      },
    });

    expect(wrapper.text()).toContain('Row 7');
    expect(wrapper.text()).toContain('Invalid');
    await wrapper.get('input[type="checkbox"]').setValue(true);
    await wrapper.get('button').trigger('click');
    expect(wrapper.emitted('retry')).toEqual([['run-1', ['failure-1']]]);
  });

  it('renders an optional failure-report download link', () => {
    const wrapper = mount(ErrorViewer, {
      props: {
        runId: 'run-1',
        failures: [],
        reportUrl: '/bulkflow/imports/run-1/failures/report?format=csv',
      },
    });

    expect(wrapper.get('a[download]').attributes('href')).toBe('/bulkflow/imports/run-1/failures/report?format=csv');
  });
});
