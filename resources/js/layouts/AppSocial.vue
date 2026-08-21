<template>
  <div class="sidebar">
    <div class="social facebook">
      <a href=" https://www.facebook.com/Children-in-Situations-of-Conflict-Project-Africa-107126914427288 "
        target="_blank">
        <div class="social_p">
          <v-icon> mdi-facebook</v-icon>
        </div>
      </a>
    </div>
    <div class="social twitter">
      <a href=" https://twitter.com/CiscProject " target="_blank">
        <div class="social_p">
          <v-icon> mdi-twitter</v-icon>
        </div>
      </a>
    </div>
    <!-- <div class="social linkdin">
      <a href=" https://www.linkedin..com " target="_blank">
        <div class="social_p">
          <v-icon> mdi-linkedin</v-icon>
        </div>
      </a>
    </div> -->
    <div class="social youtube">
      <a href=" https://www.youtube.com/channel/UCX347XjHbSjX-7sHylJiLAw " target="_blank">
        <div class="social_p">
          <v-icon> mdi-youtube</v-icon>
        </div>
      </a>
    </div>
  </div>
</template>

<script setup>
import { defineComponent } from 'vue';

defineComponent({
  name: 'AppSocial'
});
</script>

<style scoped>
.v-icon.v-icon {
  font-size: 14px !important;
  justify-content: end;
}

a {
  text-decoration: none;
  vertical-align: middle;
  text-align: center;
  line-height: 1;
}

.social_p {
  margin: 0px;
  left: 55px;
  position: relative;
}

.sidebar {
  margin-top: 90px;
  height: auto;
  width: 0px;
  position: fixed;
  text-align: center;
  top: 35vh;
  padding: 10px;
  z-index: 2;
}

.social {
  margin-left: -80px;
  width: 130px;
  padding: 0;
  display: inline-table;
  height: 0px;
  background-color: #f1f0eb;
  box-shadow: 0px 0px 2px 0px #797777;
  cursor: pointer;
  margin-top: 4px;
  transition: all 400ms ease-in-out;
}

.social:hover {
  margin-left: -35px;
  width: 100px;
  background-color: #f1f0eb;
}

.facebook,
.twitter,
.linkdin,
.youtube {
  background-color: #f0f2f5;
  border-radius: 20px;
}
</style>
