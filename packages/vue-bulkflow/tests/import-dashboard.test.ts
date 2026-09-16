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
});
