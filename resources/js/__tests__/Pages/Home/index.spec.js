import { shallowMount } from "@vue/test-utils";
import Home from "@/pages/Home/home.vue";
import { globalTestData } from "../../../test.global-data";

describe("testing home component", () => {
  test('Home.vue', () => {
    const wrapper = shallowMount(Home, {
      mocks: { $page: { props: { ...globalTestData } } }
    });

    expect(wrapper.exists()).toBe(true);
  });
});
