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

  it('prevents confirmation until every header has a unique destination', async () => {
    const wrapper = mount(ImportWizard, {
      props: { headers: ['Name', 'Email'], destinations: ['name', 'email'] },
    });

    const selects = wrapper.findAll('select');
    await selects[0].setValue('name');
    await wrapper.get('form').trigger('submit');

    expect(wrapper.emitted('confirm')).toBeUndefined();
    expect(wrapper.get('[role="alert"]').text()).toContain('Map every source column');

    await selects[1].setValue('name');
    await wrapper.get('form').trigger('submit');

    expect(wrapper.emitted('confirm')).toBeUndefined();
    expect(wrapper.get('[role="alert"]').text()).toContain('unique destination');
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

  it('emits a selected file before rendering the mapping controls', async () => {
    const wrapper = mount(ImportWizard, {
      props: { headers: [], destinations: ['name', 'email', 'password'] },
    });

    const input = wrapper.get('input[type="file"]');
    const file = new File(['name\nNeda'], 'users.csv');
    Object.defineProperty(input.element, 'files', { value: [file] });
    await input.trigger('change');

    expect(wrapper.emitted('upload')?.[0][0]).toBeInstanceOf(File);
    expect(wrapper.find('form').exists()).toBe(false);
  });

  it('applies only current-file headers when a mapping template is selected', async () => {
    const wrapper = mount(ImportWizard, {
      props: {
        headers: ['Email', 'Name'], destinations: ['email', 'name'],
        templates: [{ id: 'vendor', name: 'Vendor export', mapping: { Email: 'email', Missing: 'name' } }],
      },
    });

    await wrapper.get('[aria-label="Mapping template"]').setValue('vendor');
    const selects = wrapper.findAll('select');
    expect((selects[1].element as HTMLSelectElement).value).toBe('email');
    expect((selects[2].element as HTMLSelectElement).value).toBe('');
    expect(wrapper.get('[role="alert"]').text()).toContain('not present in this file');
  });
});
