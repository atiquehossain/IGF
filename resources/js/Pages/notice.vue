<template>
  <!-- Publication -->
  <layout>
    <app-banner-page />
    <div class="container">
      <v-row>
        <v-col md="2" cols="3">
          <div class="date-picker">
            <label class="visually-hidden" for="notice-date">Filter notices by date</label>
            <input
              id="notice-date"
              v-model="selectValue"
              class="form-control mb-3"
              type="date"
              @change="filterData()"
            >
          </div>
        </v-col>

        <v-col md="7" cols="2" />
        <v-col md="3" cols="10">
          <div class="input-group mb-3">
            <input
              v-model="searchvalue"
              type="text"
              class="form-control"
              placeholder="Search.."
              @keydown.enter="filterData()"
            />
            <div class="input-group-append">
              <button class="btn search-btn" @click="filterData()">
                <i class="fas fa-search"></i>
              </button>
            </div>
          </div>
        </v-col>
      </v-row>
    </div>
    <div class="container reports">
      <v-row class="mt-6">
        <v-col
          v-for="item in items"
          :key="item.id"
          md="4"
          cols="12"
          align="center"
          justify="center"
        >
          <div class="resource-card">
            <a
              :href="route('resources.publication.download', item.file_path)"
              target="_blank"
            >
              <v-img class="resource-img" :src="imagePath(item)" />
            </a>
            <div>
              <v-row class="resource-details">
                <v-col md="9"></v-col>
                <v-col md="3">
                  <a :href="route('notice.download', item.file_path)" target="_blank">
                    <v-icon color="#77c720">mdi-download</v-icon>
                  </a>
                  <a :href="route('notice.pdfViewer', item.file_path)" target="_blank">
                    <i style="color: #77c720" class="fa fa-book" aria-hidden="true"></i>
                  </a>
                  <a :href="item.url" target="_blank">
                    <i
                      style="color: #77c720"
                      class="fa fa-external-link ml-2"
                      aria-hidden="true"
                    ></i>
                  </a>
                  <!-- <p>
                    Website link: <br />
                    <a target="_blank" :href="item.url">{{ item.url }}</a>
                  </p> -->
                </v-col>
              </v-row>

              <p class="resource-details">
                {{ item.title }}
              </p>
              <p class="resource-details">
                {{ item.date_at }}-{{ bytesToSize(item.file_size) }}
              </p>
            </div>
          </div>
        </v-col>
      </v-row>
    </div>

    <div class="text-center">
      <v-pagination
        v-if="properties.total_page > 1"
        v-model="properties.page"
        class="ma-6"
        :length="properties.total_page"
        circle
        @input="onPageChange"
      ></v-pagination>
    </div>
  </layout>
</template>

<script>
import Layout from '../layouts/App';
import AppBannerPage from './../component/banner';
export default {
  name: 'Notice',
  components: {
    Layout,
    AppBannerPage
  },
  data () {
    return {
      page: 1,
      file_type_list: [],
      searchvalue: '',
      selectValue: '',
      items: [],
      properties: {}
    };
  },

  mounted () {
    this.properties = this.$page.props?.properties;
    this.items = this.$page.props.data?.items;
    this.file_type_list = this.$page.props.data?.resourceType;
  },

  methods: {
    imagePath (item) {
      let img_path = '/image/no-image.png';
      const fileExtension = ['csv', 'xls', 'pdf', 'xlsx', 'docx', 'doc'];
      const file_type = item.file_type;

      if (fileExtension.indexOf(file_type) > -1) {
        img_path = this.asset(`/image/${file_type}.png`);
      }

      if (item?.image_path) {
        img_path = this.asset('/storage/photos/1/notice_board/' + item.image_path);
      }
      return img_path;
    },

    async filterData () {
      try {
        const response = await axios.get(route('api.frontend.notice'), {
          params: {
            page: 1,
            search_date: this.selectValue,
            search: this.searchvalue,
            file_path: 'notice_board',
            type: 'notice-board'
          }
        });
        if (response.data.status) {
          this.items = response.data.data?.items;
          this.properties = response.data.properties;
        }
      } catch {
        this.$toast.error('Unable to load notices. Please try again.');
      }
    },

    async onPageChange (page) {
      try {
        const response = await axios.get(route('api.frontend.notice'), {
          params: {
            page,
            search_date: this.selectValue,
            search: this.searchvalue,
            file_path: 'notice_board',
            type: 'notice-board'
          }
        });
        if (response.data.status) {
          this.items = response.data.data?.items;
          this.properties = response.data.properties;
        }
      } catch {
        this.$toast.error('Unable to load notices. Please try again.');
      }
    }
  }
};
</script>
<style scope></style>
