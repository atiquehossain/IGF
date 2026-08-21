<template>
    <v-container fluid class="bg-lumber py-10">
        <v-container>
            <!-- Title and Arrows -->
            <v-row class="mb-4" align="center">
                <v-col cols="12" md="7">
                    <h2>Voices From Our Community</h2>
                </v-col>
                <v-col cols="12" md="5" class="text-md-end mt-4 mt-md-0">
                    <div class="d-flex justify-end gap-2">
                        <v-btn icon @click="prev">
                            <v-icon color="black">mdi-chevron-left</v-icon>
                        </v-btn>
                        <v-btn icon @click="next">
                            <v-icon color="black">mdi-chevron-right</v-icon>
                        </v-btn>
                    </div>
                </v-col>
            </v-row>

            <!-- Testimonial Carousel -->
            <v-carousel v-model="currentSlide" hide-delimiter-background :show-arrows="false" height="auto" cycle
                class="px-4 mt-6" transition="fade-transition" reverse-transition="fade-transition">
                <v-carousel-item v-for="(slide, index) in slides" :key="`slide-${index}`">
                    <v-row>
                        <v-col v-for="(testimonial, tIndex) in slide" :key="`testimonial-${tIndex}`" cols="12" md="6">
                            <!-- Testimonial Card -->
                            <v-card class="testimonial-card pa-6 d-flex flex-column justify-space-between">
                                <div>
                                    <p class="text-subtitle-1 text-muted">
                                        "{{ testimonial.testimonial }}"
                                    </p>
                                </div>
                                <div class="d-flex align-center mt-6">
                                    <v-avatar size="64" class="me-3">
                                        <v-img :src="testimonial.photo" alt="Author" cover />
                                    </v-avatar>
                                    <div>
                                        <div class="text-body-1 font-weight-medium text-dark">
                                            {{ testimonial.name }}
                                        </div>
                                        <div class="text-caption text-muted">
                                            {{ testimonial.designation }}
                                        </div>
                                    </div>
                                </div>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-carousel-item>
            </v-carousel>
        </v-container>
    </v-container>
</template>

<script setup>
import { ref, computed, watchEffect } from 'vue';
import { useDisplay } from 'vuetify';
import { usePage } from '@inertiajs/vue3';

const currentSlide = ref(0);
const { smAndDown } = useDisplay();
const inertiaPage = usePage();

const testimonials = computed(() => inertiaPage.props?.data?.testimonials || []);

function chunkArray(array, size) {
    const chunks = [];
    for (let i = 0; i < array.length; i += size) {
        chunks.push(array.slice(i, i + size));
    }
    return chunks;
}

const slides = ref([]);

watchEffect(() => {
    const perSlide = smAndDown.value ? 1 : 2;
    slides.value = chunkArray(testimonials.value, perSlide);
    // Reset carousel to first slide when screen resizes or testimonials change
    currentSlide.value = 0;
});

function prev() {
    currentSlide.value =
        (currentSlide.value + slides.value.length - 1) % slides.value.length;
}

function next() {
    currentSlide.value = (currentSlide.value + 1) % slides.value.length;
}
</script>

<style scoped lang="scss">
@use "../../../../scss/variables" as *;

.bg-lumber {
    background-color: $lumber;
}

.testimonial-card {
    border-radius: 30px;
    background-color: $white;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
    height: 260px !important;
}

.text-muted {
    color: $text-muted !important;
}
</style>
