<template>
    <v-container fluid class="news-bg">
        <v-container class="py-12">
            <!-- Section Title -->
            <v-row>
                <v-col>
                    <h2>Award & Recognition</h2>
                </v-col>
            </v-row>

            <!-- Cards Row with Gap -->
            <v-row class="justify-center mt-4" dense no-gutters>
                <v-col cols="12" sm="8" md="6" lg="4" class="px-4 pb-8" v-for="(award, index) in awards" :key="index">
                    <v-card class="h-100 pa-4 rounded-xl elevation-2">
                        <v-img :src="award.thumbnail" :alt="award.title" class="mb-4" />

                        <v-card-title class="text-h5 font-weight-bold ">{{ award.name }}</v-card-title>
                        <v-card-text>
                            <p class="text-body-1 text-medium-emphasis mb-4">{{ award.sub_title }}</p>
                            <div class="text-end mb-4">
                                <a :href="route('frontend.page', award.slug )" class="learn-more">Learn More</a>
                            </div>
                        </v-card-text>

                    </v-card>
                </v-col>
            </v-row>

            <!-- View All -->
            <div class="view-all mt-4">
                <a :href="route('frontend.category', 'awards-&-recognition')">View All</a>
            </div>
        </v-container>
    </v-container>
</template>

<script setup>
import { defineComponent, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineComponent({
    name: 'AwardsRecognition',
})
const inertiaPage = usePage();

const awards = computed(() => inertiaPage.props?.data?.awardRecognitions ?? []);
</script>

<style scoped lang="scss">
@use "../../../../scss/variables" as *;

.news-bg {
    background-color: $news-event-bg;
}
</style>
