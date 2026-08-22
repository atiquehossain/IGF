<template>
  <layout>
    <div class="container">
      <div>
        <h1 class="dashboard-title text-center">
          MEMBERS
        </h1>
        <v-row>
          <v-col md="9" cols="2" />
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

        <div class="members-list">
          <v-row
            v-for="(memberArr, i) in members"
            :key="i"
            align="center"
            class="d-flex mt-6"
          >
            <!-- eslint-disable-next-line vue/no-template-shadow -->
            <v-col v-for="member in memberArr" :key="member.id" cols="6" md="3">
              <div class="member" @click="openDialog(member)">
                <img class="member-logo hover-zoom-out px-20" :src="member.path" :alt="member.name" />
              </div>
            </v-col>
          </v-row>
        </div>
      </div>
    </div>
    <div class="text-center">
      <v-dialog v-model="dialog" content-class="elevation-0">
        <!-- <div class="text-center"> -->
        <v-card class="members-details">
          <i class="far fa-times fa-2x cancle-icon" @click="dialog = false"></i>
          <v-row>
            <v-col md="4" cols="4">
              <img class="member-logo-modal" :src="member.path" :alt="member.name" />
            </v-col>
            <v-col md="8" cols="8">
              <div class="members-info">
                <h4>{{ member.name }}</h4>
                <p>{{ member.description }}</p>
                <p>
                  Website link: <br />
                  <a v-if="safeMemberUrl(member.url)" target="_blank" rel="noopener noreferrer" :href="safeMemberUrl(member.url)">{{ member.url }}</a>
                </p>
              </div>
            </v-col>
          </v-row>
        </v-card>
        <!-- </div> -->
      </v-dialog>
    </div>
    <br /><br /><br />
  </layout>
</template>
<script>
import Layout from '../layouts/App';

export function safeMemberUrl (value) {
  const url = typeof value === 'string'
    ? [...value.trim()].filter((character) => {
      const code = character.charCodeAt(0);
      return code > 31 && code !== 127 && !/\s/u.test(character);
    }).join('')
    : '';
  if (!url) return '';
  if (url.startsWith('/') && !url.startsWith('//')) return url;
  try {
    const parsed = new URL(url);
    return ['http:', 'https:'].includes(parsed.protocol) ? url : '';
  } catch {
    return '';
  }
}

export default {
  components: {
    Layout
    // AppBannerPage,
  },
  data: () => ({
    overlay: false,
    dialog: false,
    member: {},
    searchvalue: '',
    members: []
  }),
  computed: {
    pageTitle () {
      return this.$page.props.title;
    }
  },
  mounted () {
    this.members = this.$page.props.data?.ourMembers;
  },
  methods: {
    safeMemberUrl,
    openDialog (member) {
      this.dialog = true;
      this.member = member;
    },
    async filterData () {
      try {
        const response = await axios.get(route('api.frontend.members'), {
          params: {
            search: this.searchvalue
          }
        });
        if (response.data.status) {
          this.members = response.data.data?.members;
        }
      } catch {
        this.$toast.error('Unable to load members. Please try again.');
      }
    }
  }
};
</script>
<style scoped>

</style>
