<template>
  <div class="container">
    <v-row>
      <v-col lg="8" md="12" cols="12" class="mt-6">
        <v-list-group :value="true">
          <template #activator>
            <v-list-item-title>
              All Comments ({{ total_comment }})
            </v-list-item-title>
          </template>

          <div>
            <v-list-item-title>
              <v-card elevation="0" class="ml-2">
                <v-row v-for="(comment, index) of comments" :key="index">
                  <v-col lg="2" md="2" cols="2">
                    <v-img
                      class="mt-5"
                      :src="'/image/no-user.png'"
                      max-width="50px"
                      max-height="100px"
                    />
                  </v-col>
                  <v-col lg="8" md="8" cols="8">
                    <v-card color="#F1F0EB">
                      <div class="ma-4">
                        <h5>{{ comment.name ? comment.name : "Anonymous" }}</h5>
                        <p>{{ comment.text }}</p>
                      </div>
                    </v-card>
                    <v-row>
                      <v-col md="2" cols="4">
                        <p>{{ totalDay(comment.date_at) }} d</p>
                      </v-col>
                      <v-col md="8" cols="4"></v-col>
                      <v-col md="2" cols="4">
                        <p class="like-pointer" @click="onLike(comment.id)">
                          Like ({{ comment.total_like }})
                        </p>
                      </v-col>
                    </v-row>
                  </v-col>
                </v-row>
              </v-card>
            </v-list-item-title>
          </div>
        </v-list-group>
      </v-col>
    </v-row>
    <v-form @submit="onSubmit">
      <v-col>
        <v-list-item-title> &nbsp; &nbsp;Leave a Reply</v-list-item-title>
        <br />
        <v-textarea
          rows="2"
          class="input-field"
          v-model="form.text"
          dense
          :error-messages="form.errors.text"
        ></v-textarea>
        <div class="m-b-20 text-right">
          <v-btn
            :disabled="form.text.length > 4 ? false : true"
            class="mt-5 btn-message-red"
            type="submit"
            outlined
            large
          >
            Send
          </v-btn>
        </div>
      </v-col>
    </v-form>

    <b-modal v-model="modalShow" id="modal-1" title="Enter Your Name">
      <div class="d-block">
        <v-text-field
          v-model="form.name"
          label="Name"
          type="text"
          outlined
          dense
        />
      </div>
      <template #modal-footer>
        <button
          v-b-modal.modal-close_visit
          @click="methodClose"
          class="btn btn-secondary btn-sm m-1"
        >
          skip
        </button>
        <button
          v-b-modal.modal-close_visit
          :disabled="form.name.length > 3 ? false : true"
          @click="onSubmitOk"
          class="btn btn-primary btn-sm m-1"
        >
          Ok
        </button>
      </template>
    </b-modal>
    <app-loading :isLoading="isLoading" />
  </div>
</template>
<style scoped>
.like-pointer {
  cursor: pointer;
}
.v-input {
  padding-left: 5px !important;
}
.icon_hide .v-list-item__icon {
  display: none;
}
.input-field {
  border: 1px solid rgb(10, 10, 10);
  border-radius: 5px;
}
/* .v-text-field--outlined > .v-input__control > .v-input__slot {
  min-height: 115px !important;
} */
.v-list-item__title {
  align-self: center;
  font-size: 20px;
}
.btn-message-red {
  border-radius: 10px;
  background-color: white;
}
.btn-message-red:hover {
  color: white;
  background: #da291c;
}
.v-list-item__title,
.v-list-item__subtitle {
  flex: 0 0 20%;
}
</style>
<script>
import axios from "axios";
import AppLoading from "./../Shared/loading";

export default {
  name: "Commant",
  components: {
    AppLoading,
  },
  data() {
    return {
      isLoading: false,
      modalShow: false,
      form: this.$inertia.form({
        name: "",
        text: "",
      }),
      total_comment: 0,
      comments: [],
    };
  },
  methods: {
    totalDay(date_at) {
      const date1 = new Date(date_at);
      const date2 = new Date();
      const diffTime = Math.abs(date2 - date1);
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      return diffDays;
    },
    onSubmit(event) {
      event.preventDefault();
      this.modalShow = true;
    },
    onSubmitOk(event) {
      event.preventDefault();
      this.modalShow = false;
      this.isLoading = true;
      this.onComment();
    },
    methodClose(event) {
      event.preventDefault();
      this.modalShow = false;
      this.isLoading = true;
      this.onComment();
    },
    async onLike(comment_id) {
      this.isLoading = true;
      try {
        const response = await axios.post(route("api.frontend.like"), {
          comment_id: comment_id,
        });
        this.comments = this.comments?.map((r) => {
          if (r.id == comment_id) {
            r.total_like = response.data.total_like;
          }
          return r;
        });
        setTimeout(() => (this.isLoading = false), 500);
      } catch {
        setTimeout(() => (this.isLoading = false), 500);
      }
    },
    async onComment() {
      const page_id = this.$page.props.data?.page?.id;
      try {
        const response = await axios.post(route("api.frontend.comment"), {
          page_id: page_id,
          text: this.form?.text,
          name: this.form?.name ?? "Anonymous",
        });
        if (response.data.status == true) {
          this.comments = response.data?.data;
          this.total_comment = response.data?.total_comment;
          this.form.text = "";
        } else {
          this.$toast.error(response.data.message);
        }
        setTimeout(() => (this.isLoading = false), 500);
      } catch {
        setTimeout(() => (this.isLoading = false), 500);
      }
    },
  },
  mounted() {
    this.comments = this.$page.props.data?.comment;
    this.total_comment = this.$page.props.data?.total_comment;
  },
};
</script>
