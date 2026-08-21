<template>
  <div class="">
    <v-card v-if="!isHidden" class="cookies card-left">
      <div class="container">
        <v-row>
          <v-col md="3" cols="3">
            <v-img class="cookies-icon"
              src="https://raw.githubusercontent.com/s1mpson/-/master/codepen/we-use-cookies/cookie.png" alt="cookie" />
          </v-col>

          <v-col md="9" cols="9">
            <p class="cookies-details">
              <b>Our site Uses Cookies</b> By Clicking Agree, You agree to our
              <a href="#" class="cookies-details">cookies policy</a>
            </p>
          </v-col>
        </v-row>
        <v-row class="mt-n6">
          <v-col md="12" cols="12">
            <button text class="cancle-btn" @click="isHidden = true">
              Cancel
            </button>
            <button class="ml-5 btn-cookies" @click="saveCookie()">
              Allow Cookies
            </button>
          </v-col>
        </v-row>
      </div>
    </v-card>
  </div>
</template>

<script setup>
import { ref, onMounted, defineComponent } from 'vue';
import { useGlobal } from '../Shared/composables/global';

defineComponent({
  name: 'AppCookies'
});

const isHidden = ref(false);
const { $cookies } = useGlobal();

// Methods
const getCookie = () => {
  if ($cookies.get('app_cookies')) {
    isHidden.value = true;
  }
};

const saveCookie = () => {
  $cookies.set('app_cookies', true);
  getCookie();
};

onMounted(() => {
  getCookie();
});
</script>

<style scoped>
.card-left {
  animation: fadeInLeft 3s ease-in-out;
}

@keyframes fadeInLeft {
  0% {
    opacity: 0;
    transform: translateX(-400px);
  }

  100% {
    opacity: 1;
  }
}

.cookies-icon {
  left: 15%;
  height: 60px;
  width: 60px;
  animation: spin 2s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}

.cookies-details {
  font-size: 15px !important;
}

.v-application a {
  text-transform: inherit;
}

.btn-cookies {
  border: 1px solid black;
  border-radius: 10px;
  padding: 4px;
}

.btn-cookies:hover {
  background-color: #77c720;
  color: white;
}

.cancle-btn:hover {
  color: red;
  font-size: 15px;
  font-weight: bold;
}

.v-application a {
  text-decoration: underline;
  color: black;
}

.cookies {
  right: 0;
  /* width: 100%; */
  width: 380px;
  position: fixed;
  text-align: center;
  bottom: 10px;
  z-index: 2;
  background: rgb(236 246 236 / 96%);
  color: #0b0a0a;
}

@media (max-width: 480px) {
  .btn-cookies {
    margin-right: 10px;
  }
}
</style>
