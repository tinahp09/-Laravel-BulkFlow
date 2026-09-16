import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ImportWizard from '../src/components/ImportWizard.vue';

describe('ImportWizard', () => {
  it('emits the selected source-to-destination mapping on confirmation', async () => {
    const wrapper = mount(ImportWizard, {
      props: { headers: ['نام', 'ایمیل'], destinations: ['name', 'email'] },
    });

    const selects = wrapper.findAll('select');
    await selects[0].setValue('name');
    await selects[1].setValue('email');
    await wrapper.get('form').trigger('submit');

    expect(wrapper.emitted('confirm')).toEqual([[{ نام: 'name', ایمیل: 'email' }]]);
  });

  it('shows a sample preview before mapping when preview rows are provided', async () => {
    const wrapper = mount(ImportWizard, {
      props: {
        headers: ['نام', 'ایمیل'],
        destinations: ['name', 'email'],
        previewRows: [{ نام: 'ندا', ایمیل: 'neda@example.test' }],
      },
    });

    expect(wrapper.get('[aria-label="Import preview"]').text()).toContain('neda@example.test');
    await wrapper.get('button').trigger('click');
    expect(wrapper.get('form').exists()).toBe(true);
  });
});
