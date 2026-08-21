<!-- eslint-disable vue/no-v-html -->
<template>
  <layout>
    <app-banner-page />
    <v-container>
      <v-row md12>
        <br /><br /><br />
        <h1>{{ title }}</h1>
        <v-divider class="divider"></v-divider>
      </v-row>
    </v-container>
    <v-container>
      <v-row>
        <div v-for="page in pages" :key="page.id" class="col-md-4">
          <h2>{{ page.name }}</h2>
          <article v-html="page.description" />
        </div>
      </v-row>
    </v-container>
  </layout>
</template>

<script>
import Layout from '../layouts/App';
import AppBannerPage from './../component/banner';

export default {
  components: {
    Layout,
    AppBannerPage
  },
  computed: {
    title () {
      return this.$page.props.title;
    },
    pages () {
      return this.$page.props.data.items.page;
    }
  }
};
</script>
