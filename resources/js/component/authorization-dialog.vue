<template>
  <div>
    <div class="text-center">
      <v-dialog :model-value="showDialog"
        @update:model-value="updateShowDialog"
        width="500"
        content-class="authorization-dialog"
        @click:outside="toggleDialog"
        >
        <v-card>
          <v-card-title class="text-h5 grey lighten-2 header-section">
            <h2 class="title">
              Welcome Back! Please <span>Log In</span> Here
            </h2>
          </v-card-title>

          <v-card-text class="content-section">
            <div class="data">
              <!-- Login -->
              <v-form @submit.prevent="login">
                <div v-if="currentTab === 'login'" class="form-group">
                  <div class="input">
                    <label for="">Phone Number <sup>*</sup></label>
                    <v-text-field v-model="phone_no" type="number" class="ecw-input" solo
                      label="Enter your Phone Number" append-icon="mdi-phone"></v-text-field>
                  </div>
                  <div class="input">
                    <label for="">Password <sup>*</sup></label>
                    <v-text-field v-model="password" type="password" class="ecw-input" solo label="Enter your Password"
                      append-icon="mdi-eye"></v-text-field>
                  </div>
                  <div class="context">
                    <div class="remember">
                      <v-checkbox v-model="checkbox" class="ecw-checkbox"></v-checkbox>
                      <p>Remember Me</p>
                    </div>
                    <p class="forget-password">
                      Forgot Password?
                    </p>
                  </div>
                  <div class="buttons">
                    <button class="login-btn" type="submit">
                      Login
                    </button>
                    <!-- <p>Or continue with</p> -->
                  </div>
                </div>
              </v-form>
              <!-- /Login -->
              <!-- signup -->
              <v-form @submit.prevent="register">
                <div v-if="currentTab === 'signup' && showCreateAccountInputs" class="form-group">
                  <div class="input">
                    <label for="">Full Name</label>
                    <v-text-field v-model="name" type="text" class="ecw-input" solo label="Enter your Full Name"
                      append-icon="mdi-account-circle"></v-text-field>
                  </div>
                  <div class="input">
                    <label for="">Phone Number <sup>*</sup></label>
                    <v-text-field v-model="phone_no" type="text" class="ecw-input" solo label="Enter your Phone Number"
                      append-icon="mdi-phone"></v-text-field>
                  </div>
                  <div class="input">
                    <label for="">Email Address </label>
                    <v-text-field v-model="email" type="email" class="ecw-input" solo label="Enter your Email address"
                      append-icon="mdi-email"></v-text-field>
                  </div>
                  <div class="input">
                    <label for="">Organization <sup>*</sup></label>
                    <v-text-field v-model="org" type="email" class="ecw-input" solo label="Enter your Email address"
                      append-icon="mdi-email"></v-text-field>
                  </div>

                  <div class="input">
                    <label for="">Designation <sup>*</sup></label>
                    <v-select v-model="designation" chips :items="designation_list" label="Select Designation" solo
                      class="ecw-input" append-icon="mdi-briefcase-account"></v-select>
                  </div>

                  <div class="input">
                    <label for="">Password <sup>*</sup></label>
                    <v-text-field v-model="password" type="password" class="ecw-input" solo label="Enter your Password"
                      append-icon="mdi-eye"></v-text-field>
                  </div>
                  <div class="buttons">
                    <button class="login-btn" type="submit">
                      Signup
                    </button>
                  </div>
                </div>
              </v-form>
              <!-- /signup -->
              <div class="buttons">
                <template v-if="currentTab === 'signup' && !showCreateAccountInputs">
                  <button class="login-btn" :style="{ width: '100%' }" @click="
                      {
                    !showCreateAccountInputs
                      ? (showCreateAccountInputs = !showCreateAccountInputs)
                      : register();
                  }
                    ">
                    Create new account with Email
                  </button>
                  <!-- <p>Or</p> -->
                </template>
              </div>
            </div>
          </v-card-text>

          <v-card-actions class="actions">
            <p v-if="currentTab === 'login'">
              Don't have an account?
              <span @click="currentTab = 'signup'">Create one here</span>
            </p>
            <p v-if="currentTab === 'signup'" :style="{ 'margin-top': showCreateAccountInputs ? '-50px' : '' }">
              Already have an account?
              <span @click="() => { currentTab = 'login'; showCreateAccountInputs = false }">Login here</span>
            </p>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AuthorizationDialog',
  props: {
    showDialog: {
      type: Boolean,
      default: false
    },
    toggleDialog: {
      type: Function,
      required: true
    }
  },
  emits: ['update:showDialog'],
  data: () => ({
    modalOpen: true,
    checkbox: false,
    currentTab: 'login',
    showCreateAccountInputs: false,
    email: '',
    password: '',
    name: '',
    phone_no: '',
    org: '',
    designation: '',
    remember_me: false,
    user: [],
    designation_list: [
      "Technical Officer",
      "Project Officer",
      "Program Officer",
      "Field Officer",
      "Program Organizer",
      "Education Organizer",
      "Manager",
      "Technical Manager",
      "Technical Specialist",
      "EiE Specialist",
      "Quality Assurance Specialist",
      "National Teacher"
    ]
  }),
  methods: {
    googleLogin() {
      window.location.href = route('login.google', [this.igfLocale]);
    },
    facebookLogin() {
      window.location.href = route('login.facebook', [this.igfLocale]);
    },
    login() {
      this.$inertia.post(route('login', [this.igfLocale]), {
        phone_no: this.phone_no,
        password: this.password,
        remember_me: false
      });
    },
    register() {
      this.currentTab = 'signup';
      this.$inertia.post(route('register', [this.igfLocale]), {
        name: this.name,
        phone_no: this.phone_no,
        org: this.org,
        designation: this.designation,
        email: this.email,
        password: this.password
      });
    },
    updateShowDialog(value) {
      this.$emit('update:showDialog', value);  // Emit the event to update showDialog prop
    }
  }
};
</script>

<style lang="scss" scoped>
.authorization-dialog {
  width: calc(30% + 100px);

  .v-card {
    display: flex;
    flex-direction: column;
    row-gap: 36px;
    padding: 50px;

    &__title {
      background-color: transparent !important;
      padding: 0;
      width: 100%;
      display: flex;
      justify-content: center;
      align-items: center;

      .title {
        font-size: 20px !important;
        text-align: left;
        line-height: 46px;
        color: #373b3f;
        margin: 0;

        span {
          color: #ff7828;
        }
      }
    }

    .content-section {
      .data {
        display: flex;
        flex-direction: column;
        row-gap: 26px;

        .form-group {
          display: flex;
          flex-direction: column;
          row-gap: 28px;

          .input {
            display: flex;
            flex-direction: column;
            row-gap: 5px;

            label {
              font-size: 12px;
              font-weight: bold;
              line-height: 15px;
              text-align: left;
              color: #1c2e40;

              sup {
                font-size: 14px;
                line-height: 18px;
                color: #da291c;
                text-align: left;
              }
            }

            .ecw-input {
              .v-input__control {
                .v-text-field__details {
                  display: none;
                }

                .v-input__slot {
                  margin-bottom: 0;
                  border-radius: 4px;
                  box-shadow: unset !important;
                  border: 1px solid rgb(112 112 112 / 50%);
                  padding: 13px 16px;
                }
              }
            }
          }

          .context {
            display: flex;
            align-items: center;
            justify-content: space-between;

            .remember {
              display: flex;
              align-items: center;
              column-gap: 12px;

              .v-input {
                margin: 0;
                padding: 0;

                &__control {
                  .v-input__slot {
                    margin-bottom: 0 !important;
                  }

                  .v-messages {
                    display: none !important;
                  }
                }
              }

              p {
                margin: 0;
                font-size: 14px;
                color: #1c2e40;
                line-height: 18px;
                text-align: left;
              }
            }

            .forget-password {
              margin: 0;
              font-size: 14px;
              text-align: left;
              color: #ff7828;
              line-height: 18px;
            }
          }
        }

        .buttons {
          display: flex;
          flex-direction: column;
          row-gap: 20px;

          .login-btn {
            width: 100%;
            background: #ff7828;
            color: #fff;
            border-radius: 4px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            text-align: left;
            line-height: 20px;
          }

          p {
            margin: 0;
            text-align: center;
            font-size: 16px;
            line-height: 20px;
            color: #24242b;
            font-weight: 400;
          }

          .social-btn-list {
            display: flex;
            justify-content: space-between;
            column-gap: 12px;

            button {
              width: 50%;
              border: 1px solid #bababa;
              height: 52px;
              border-radius: 4px;
              font-size: 16px;
              text-align: left;
              display: flex;
              justify-content: center;
              align-items: center;
              color: #24242b;
              column-gap: 8px;

              img {
                width: 26px;
              }

              a {
                font-size: 16px;
                color: #24242b;
              }
            }
          }

          .social-btn-vertical {
            flex-direction: column;
            row-gap: 12px;

            button {
              width: 100%;
            }
          }
        }
      }
    }

    .actions {
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;

      p {
        margin: 0;
        font-size: 14px;
        text-align: left;
        line-height: 18px;
        color: rgb(36 36 43 / 65%);
        display: flex;
        column-gap: 6px;

        span {
          font-size: 14px;
          font-weight: bold;
          color: #da291c;
          text-align: left;
          line-height: 18px;
          cursor: pointer;
        }
      }
    }
  }

  @media(max-width: 550px) {
    .v-card {
      padding: 20px;
    }
  }
}
</style>
