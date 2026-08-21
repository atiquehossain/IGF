<template>
    <v-container fluid class="bg-upcoming-events text-white pa-0">
        <div class="overlay-dark">
            <v-container class="py-12">
                <h2>Upcoming Events</h2>

                <!-- Cause Cards Row -->
                <v-row justify="center" class="mt-8">
                    <!-- Cause Card 1 -->
                    <v-col v-for="(upcomingEvent, index) in upcomingEvents" :key="index" cols="12" sm="8" md="6" lg="4"
                        class="d-flex justify-center">
                        <CategoryItemCard :title="upcomingEvent.title" :subtitle="upcomingEvent.sub_title"
                            :thumbnail="upcomingEvent.image_path" :link="route('frontend.event', upcomingEvent.slug)" />
                    </v-col>

                </v-row>

                <!-- View All Button -->
                <div class="view-all mt-8">
                    <a :href="route('frontend.events')">View All</a>
                </div>
            </v-container>
        </div>
    </v-container>
</template>

<script setup>
import { defineComponent, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

import CategoryItemCard from '../../../Shared/category-item-card.vue';

defineComponent({
    name: 'Upcoming Events',
});

const inertiaPage = usePage();

const upcomingEvents = computed(() => inertiaPage.props?.data?.upcomingEvents ?? []);
</script>

<style scoped lang="scss">
@use "../../../../scss/variables" as *;

/* Background for Upcoming Events Section */
.bg-upcoming-events {
    background-image: url('../../../../images/our-causes-bg.webp');
    background-size: cover;
    background-position: center;
}
</style>
