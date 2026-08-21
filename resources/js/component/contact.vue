<template>
  <v-container class="contact-us">
    <div class="background-container">

      <v-row>
        <!-- Text Section -->
        <v-col cols="12" md="12" lg="4" class="contact-us__header">
          <h3>For more information please subscribe</h3>
        </v-col>

        <!-- Form Section -->
        <v-col cols="12" lg="8" md="12" class="contact-us__form-container">
          <v-form v-model="formValid" class="d-flex contact-us-form">
            <!-- Email Field -->
            <v-text-field v-model="email" placeholder="typeyourmail@gmail.com" :rules="emailRules" required class="mr-4 contact-form"></v-text-field>
            <!-- Contact Us Button -->
            <v-btn class="contact-us__button" @click="openDialog">Subscribe</v-btn>
          </v-form>
        </v-col>
      </v-row>

    </div>
    <!-- Custom Subscribe Dialog -->
    <v-dialog v-model="dialog" width="unset" content-class="subscribe-dialog">
        <v-card class="subscribe-card">
            <!-- Close Icon -->
            <v-btn icon class="close-btn" @click="dialog = false">
            <div class="close-icon"></div>
            </v-btn>

            <!-- Dialog Content -->
            <div class="dialog-content">
            <!-- Thumbs Up Image -->
                <div class="thumbs-up-container"></div>
                <!-- Text  -->
                <div class="text-container">
                    <h1 class="main-text"> Subscribed!</h1>
                    <p class="sub-text">Thanks for your subscription</p>
                </div>
            </div>
        </v-card>
    </v-dialog>
  </v-container>
</template>

<script>
export default {
  data() {
    return {
      formValid: false,
      email: '',
      dialog: false,
      emailRules: [
        v => !!v || 'Email is required',
        v => /.+@.+\..+/.test(v) || 'E-mail must be valid',
      ],
    };
  },
  methods: {
    openDialog() {
      if (this.formValid) {
        // alert(`Email sent to: ${this.email}`);
        this.dialog = true;
      } else {
        this.dialog = true;
        //alert('Please fill out a valid email address.');
      }
    },
  },
};
</script>

<style scoped lang="scss">
.v-sheet.v-card {
  border-radius: 18px;
}

:deep(.v-overlay__content.subscribe-dialog) {
  border-radius: 26px;
}

.subscribe-card {
  color: #000;
  width: 750px;
  height: 430px;
  background: #ffffff;
  border: 1px solid #707070;
  border-radius: 26px;
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;

  @media (max-width: 1200px) {
    width: 700px;
    height: 400px;
  }

  @media (max-width: 992px) {
    width: 600px;
    height: 350px;
  }

  @media (max-width: 768px) {
    width: 500px;
    height: 300px;
  }

  @media (max-width: 576px) {
    width: 350px;
    height: 250px;
  }

  @media (max-width: 400px) {
    width: 300px;
    height: 220px;
  }

  .close-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    z-index: 10;

    @media (max-width: 768px) {
      top: 10px;
      right: 10px;
    }

    .close-icon {
      width: 25px;
      height: 25px;
      background-image: url('/frontend/images/contact/Icon metro-cross.png');
      background-size: contain;
      background-repeat: no-repeat;
      background-position: center;

      @media (max-width: 576px) {
        width: 20px;
        height: 20px;
      }
    }
  }

  .dialog-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    width: 100%;
    padding: 1.5rem;
    gap: 0;

    @media (max-width: 768px) {
      padding: 1rem;
    }

    .thumbs-up-container {
      width: 590px;
      height: 320px;
      background-image: url('/frontend/images/contact/freepik__the-style-is-3d-model-with-octane-render-volumetri__67983.png');
      background-size: contain;
      background-repeat: no-repeat;
      background-position: center;
      margin-bottom: 0;

      @media (max-width: 992px) {
        width: 100%;
        height: 180px;
      }

      @media (max-width: 768px) {
        width: 90%;
        height: 140px;
      }

      @media (max-width: 576px) {
        width: 85%;
        height: 120px;
      }
    }

    .text-container {
      text-align: center;
      margin-top: -30px;

      @media (max-width: 768px) {
        margin-top: -15px;
      }

      .main-text {
        font-weight: bold;
        color: #DA291C;
        margin-bottom: 6px;
        font-size: 3.4rem;
        line-height: 1.1;

        @media (max-width: 992px) {
          font-size: 2.8rem;
        }

        @media (max-width: 768px) {
          font-size: 2.2rem;
        }

        @media (max-width: 576px) {
          font-size: 1.8rem;
          margin-bottom: 4px;
        }
      }

      .sub-text {
        color: #1b1919;
        max-width: 500px;
        font-size: 1rem;

        @media (max-width: 992px) {
          font-size: 0.95rem;
          max-width: 400px;
        }

        @media (max-width: 768px) {
          font-size: 0.9rem;
          max-width: 300px;
        }

        @media (max-width: 576px) {
          font-size: 0.85rem;
          max-width: 250px;
        }
      }
    }
  }
}

:deep(input) {
  color: #000 !important;
}
</style>

