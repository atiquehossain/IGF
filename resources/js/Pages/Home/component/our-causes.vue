<template>
    <v-container fluid class="bg-causes text-white pa-0">
        <div class="overlay-dark">
            <v-container class="py-12">
                <h2>Our Causes</h2>

                <!-- Cause Cards Row -->
                <v-row justify="center" class="mt-8">
                    <!-- Cause Card 1 -->
                    <v-col v-for="(ourCause, index) in ourCauses" :key="index" cols="12" sm="8" md="6" lg="4"
                        class="d-flex justify-center">
                        <CategoryItemCard :title="ourCause.name" :subtitle="ourCause.sub_title"
                            :thumbnail="ourCause.thumbnail" :link="route('frontend.page', ourCause.slug)" />
                    </v-col>

                </v-row>

                <!-- View All Button -->
                <!-- <div class="view-all mt-8">
                    <a :href="route('frontend.category', 'our-causes')">View All</a>
                </div> -->
            </v-container>
        </div>
    </v-container>
</template>

<script setup>
import { defineComponent, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

import CategoryItemCard from '../../../Shared/category-item-card.vue';

defineComponent({
    name: 'Our Causes',
});

const inertiaPage = usePage();

const ourCauses = computed(() => inertiaPage.props?.data?.ourCauses ?? []);
</script>

<style scoped lang="scss">
@use "../../../../scss/variables" as *;

/* Background for Our Causes Section */
.bg-causes {
    background-image: url('../../../../images/our-causes-bg.webp');
    background-size: cover;
    background-position: center;
}
</style>
