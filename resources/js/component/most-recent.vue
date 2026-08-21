<template>
  <div v-if="mostRecents" class="pb-15">
    <!-- Start Most Recent -->

    <div class="recent-post-list">
      <v-row>
        <h3 class="text-center mt-6 side-post">
          The Most Recent
        </h3>
        <v-col v-for="(post, index) in mostRecents" :key="index" cols="6" md="12">
          <v-card elevation="0" color="#F5F5F7" class="ml-5">
            <v-row>
              <v-col md="6" cols="12">
                <Link
                  v-if="post.slug"
                  :href="route('frontend.page', post.slug)"
                  class="side-post title"
                >
                  <v-img :src="displayImage(post.description)" max-height="150px" :alt="post.name"></v-img>
                </Link>
              </v-col>
              <v-col md="6" cols="12" class="sidebar-details">
                <Link
                  v-if="post.slug"
                  :href="route('frontend.page', post.slug)"
                  class="side-post title"
                >
                  {{ displayText(post.name) }}
                </Link>
                <div class="sportlight-case">
                  <v-icon class="filter-btn" color="#77c720">
                    mdi-calendar
                  </v-icon>
                  {{ post.category_name }} ,
                </div>
              </v-col>
            </v-row>
          </v-card>
        </v-col>
      </v-row>
    </div>
    <!-- End Most Recent -->
  </div>
</template>

<script>
/* eslint-disable vue/require-prop-types */

export default {
  name: 'MostRecent',
  props: ['mostRecents']
};
</script>

<style scoped>
h1 span {
  display: block;
  max-width: 500px;
  word-wrap: break-word;
}

p span {
  display: block;
  max-width: 400px;
  word-wrap: break-word;
}
</style>
