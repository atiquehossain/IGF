import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import VolunteerRegistration from '@/Pages/volunteer-registration.vue'

const layoutStub = { template: '<main><slot /></main>' }
const formStub = { template: '<form><slot /></form>' }
const selectStub = {
  name: 'VSelect',
  inheritAttrs: false,
  props: {
    items: { type: Array, default: () => [] },
    itemTitle: String,
    itemValue: String,
    modelValue: [String, Number, Boolean],
  },
  emits: ['update:modelValue'],
  template: '<div v-bind="$attrs" />',
}

function select(wrapper, testId) {
  return wrapper
    .findAllComponents(selectStub)
    .find(component => component.attributes('data-test') === testId)
}

describe('volunteer registration localized choices', () => {
  test('renders localized labels while posting stable option values and location IDs', async () => {
    usePage().props = {
      siteSettings: {
        volunteer_page: {},
        shared_blocks: {},
      },
      data: {
        causes: [{ id: 9, name: 'শিক্ষা' }],
        options: {
          sex: [
            { value: 'female', label: 'নারী' },
            { value: 'male', label: 'পুরুষ' },
          ],
          occupations: [{ value: 'student', label: 'শিক্ষার্থী' }],
          education_levels: [{ value: 'bachelor_equivalent', label: 'স্নাতক' }],
          blood_groups: [{ value: 'A+', label: 'এ পজিটিভ (A+)' }],
          emergency_response_training: [{ value: true, label: 'হ্যাঁ' }],
          skills: [{ value: 'photography', label: 'ফটোগ্রাফি' }],
        },
        locations: {
          divisions: [{ id: 1, name: 'Dhaka', label: 'ঢাকা' }],
          districts: [{ id: 2, parent_id: 1, name: 'Dhaka', label: 'ঢাকা' }],
          upazilas: [{ id: 3, parent_id: 2, name: 'Savar', label: 'সাভার' }],
        },
      },
    }

    const wrapper = mount(VolunteerRegistration, {
      global: {
        stubs: {
          Layout: layoutStub,
          'v-form': formStub,
          'v-select': selectStub,
        },
        mocks: { $toast: { success: vi.fn(), error: vi.fn() } },
      },
    })

    const sex = select(wrapper, 'sex-select')
    const division = select(wrapper, 'division-select')
    expect(sex.props('itemTitle')).toBe('label')
    expect(sex.props('itemValue')).toBe('value')
    expect(sex.props('items')).toEqual([
      { value: 'female', label: 'নারী' },
      { value: 'male', label: 'পুরুষ' },
    ])
    expect(division.props('itemTitle')).toBe('label')
    expect(division.props('itemValue')).toBe('id')
    expect(division.props('items')).toEqual([{ id: 1, name: 'Dhaka', label: 'ঢাকা' }])

    division.vm.$emit('update:modelValue', 1)
    await nextTick()
    const district = select(wrapper, 'district-select')
    expect(district.props('items')).toEqual([{ id: 2, parent_id: 1, name: 'Dhaka', label: 'ঢাকা' }])

    district.vm.$emit('update:modelValue', 2)
    await nextTick()
    expect(select(wrapper, 'upazila-select').props('items'))
      .toEqual([{ id: 3, parent_id: 2, name: 'Savar', label: 'সাভার' }])

    wrapper.unmount()
  })
})
