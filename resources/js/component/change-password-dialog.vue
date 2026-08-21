<!-- eslint-disable vue/no-mutating-props -->
<template>
  <div>
    <div class="text-center">
      <v-dialog
        v-model="modalOpen"
        width="500"
        content-class="change-password-dialog"
        @click:outside="clickOutside"
      >
        <v-card>
          <v-card-title class="text-h5 grey lighten-2 header-section">
            <h2 class="title">
              Change <span>Password</span> Here
            </h2>
          </v-card-title>

          <v-card-text class="content-section">
            <div class="data">
              <!-- Login -->
              <v-form @submit.prevent="changepassword">
                <div class="form-group">
                  <div class="input">
                    <label for="">Current Password <sup>*</sup></label>
                    <v-text-field
                      v-model="current_password"
                      type="password"
                      class="ecw-input"
                      solo
                      label="Enter Current Password"
                      append-icon="mdi-eye"
                    ></v-text-field>
                  </div>
                  <div class="input">
                    <label for="">Password <sup>*</sup></label>
                    <v-text-field
                      v-model="password"
                      type="password"
                      class="ecw-input"
                      solo
                      label="Enter New Password"
                      append-icon="mdi-eye"
                    ></v-text-field>
                  </div>
                  <div class="input">
                    <label for="">Confirm Password <sup>*</sup></label>
                    <v-text-field
                      v-model="password_confirmation"
                      type="password"
                      class="ecw-input"
                      solo
                      label="Confirm New Password"
                      append-icon="mdi-eye"
                    ></v-text-field>
                  </div>
                  <div class="buttons">
                    <button
                      class="login-btn"
                      type="submit"
                    >
                      Submit
                    </button>
                  </div>
                </div>
              </v-form>
              <!-- /Login -->
            </div>
          </v-card-text>
        </v-card>
      </v-dialog>
    </div>
  </div>
</template>
<script>

export default {
  name: 'ChangePassDialog',
  // eslint-disable-next-line vue/require-prop-types
  props: ['showDialog', 'toggleDialog'],
  emits: ['toggle_dialog'],
  data: () => ({
    modalOpen: false,
    current_password: '',
    password: '',
    password_confirmation: ''
  }),
  watch: {
    $page: {
      handler () {
        if (this.$page.props.flash?.message?.type === 'success') {
          this.$emit('toggle_dialog');
        }
      }
    }
  },
  mounted() {
    this.modalOpen = this.$props.showDialog;
  },
  methods: {
    clickOutside() {
      this.$emit('toggle_dialog');
    },
    changepassword () {
      this.$inertia.post(route('change.password', [this.igfLocale]), {
        current_password: this.current_password,
        password: this.password,
        password_confirmation: this.password_confirmation
      });
    }
  }
};
</script>
<style lang="scss" scoped>
.change-password-dialog {
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

              img{
                width: 26px;
              }

              a{
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
}
</style>
