<!-- Rich text is sanitized by ContentSanitizer before it reaches this view. -->
<!-- eslint-disable vue/no-v-html -->
<template>
  <layout>
    <div>
    <div class="service-details-container">
      <h1 class="text-center service-title">{{ page.name }}</h1>
    </div>
      
      <v-container>
        <v-row md12>
         
        </v-row>
      </v-container>
      <v-container>
        <v-row md12>
          <article v-html="page.description" />
        </v-row>
      </v-container>
    </div>
  </layout>
</template>

<script>
import Layout from '../layouts/App';

export default {
  components: {
    Layout
  },
  computed: {
    page() {
      return this.$page.props.data;
    }
  }
};
</script>

<style scoped lang="scss">
.service-details-container {
  background-image: url('../../images/service-background.png');
    background-size: cover;
    background-position: center;
    .service-title {
      color: white;
      padding: 2%;
    }
}
</style>
