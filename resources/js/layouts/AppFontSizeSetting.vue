<template>
  <div class="font-size-settings">
    <div
      class="increment_descrement"
      :class="{ increment_descrement_visible: isSettingOpen }"
    >
      <div class="increment" @click="increaseFontSize">
        <p class="font-increase-icon">
          A+
        </p>
        <p class="font-increase-text">
          Increase
        </p>
      </div>
      <div class="default" @click="setFontSizeToDefault">
        <p class="font-default-icon">
          A
        </p>
        <p class="font-default-text">
          Default
        </p>
      </div>
      <div class="decrement" @click="decreaseFontSize">
        <p class="font-decrease-icon">
          A-
        </p>
        <p class="font-decrease-text">
          Decrease
        </p>
      </div>
    </div>
    <div
      class="trigger"
      :class="{ 'trigger-open': isSettingOpen }"
      @click="isSettingOpen = !isSettingOpen"
    >
      <img :src="icons.fontSize" alt="" />
      <p class="font-setting-text">
        Font Size
      </p>
    </div>
  </div>
</template>

<script>
import fontSizeIcon from '/frontend/images/static/font-size-icon.svg';

import ChangeFontSize from "./../libs/dynamic-font-size-changer";

export default {
  name: 'AppFontSizeSetting',
  data () {
    return {
      isSettingOpen: false,
      icons: {
        fontSize: fontSizeIcon
      },
      changeFontSize: ChangeFontSize()
    };
  },
  mounted() {
    this.changeFontSize = ChangeFontSize();
  },
  methods: {
    increaseFontSize() {
      this.changeFontSize("+");
      this.setFontSizeToLocalStorage("+");
    },
    decreaseFontSize() {
      this.changeFontSize("-");
      this.setFontSizeToLocalStorage("-");
    },
    setFontSizeToDefault() {
      this.changeFontSize("");
      this.setFontSizeToLocalStorage("");
    },
    setFontSizeToLocalStorage(status) {
      let savedFontSize = JSON.parse(localStorage.getItem("dynamicFontSize"));

      if (status === "+") {
        if (!savedFontSize) {
          localStorage.setItem("dynamicFontSize", "1");
          return;
        }
        savedFontSize += 1;
        localStorage.setItem("dynamicFontSize", `${savedFontSize}`);
      } else if (status === "-") {
        if (!savedFontSize) {
          localStorage.setItem("dynamicFontSize", "-1");
          return;
        }
        savedFontSize -= 1;
        localStorage.setItem("dynamicFontSize", `${savedFontSize}`);
      } else if (status === "") {
        if (!savedFontSize) {
          localStorage.setItem("dynamicFontSize", "0");
          return;
        }
        localStorage.setItem("dynamicFontSize", "0");
      }
    }
  }
};
</script>

<style scoped lang="scss">
.font-size-settings {
  position: absolute;
  top: 70vh;
  right: 0;

  .increment_descrement {
    background: #4b4b53;
    position: absolute;
    top: -214px;
    left: 0;
    width: 100%;
    padding: 12px 8px;
    border-top-left-radius: 5px;
    border-top-right-radius: 5px;
    flex-direction: column;
    row-gap: 8px;
    transition: 0.5s;
    display: none;

    .increment,
    .default,
    .decrement {
      display: flex;
      flex-direction: column;
      row-gap: 6px;
      border-radius: 5px;
      justify-content: center;
      align-items: center;
      padding: 8px 4px;
    }

    .increment,
    .decrement,
    .default {
      border: 1px solid #8f8f8f;
      cursor: pointer;

      p {
        user-select: none;
        &:nth-of-type(1) {
          margin: 0;
          font-size: 12px;
          color: #f3f1ec;
        }

        &:nth-of-type(2) {
          margin: 0;
          font-size: 12px;
          color: #f3f1ec;
          text-align: center;
          line-height: 16px;
        }
      }
    }

    .default {
      border: unset !important;
      p {
        &:nth-of-type(1) {
          margin: 0;
          font-size: 12px;
          color: #4b4b53 !important;
          width: 20px;
          height: 20px;
          border-radius: 50%;
          background: #f3f1ec;
          display: flex;
          align-items: center;
          justify-content: center;
        }
      }
    }
  }

  .increment_descrement_visible {
    display: flex;
  }

  .trigger {
    width: 71px;
    border: 1px solid #ff7828;
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    column-gap: 8px;
    padding: 10px 8px;
    background: #24242b;
    transition: 0.5s;
    cursor: pointer;

    img {
      width: 20px;
      height: 20px;
      user-select: none;
    }

    p {
      margin: 0;
      font-size: 12px;
      color: #ffffff;
      text-align: center;
      line-height: 18px;
      text-transform: capitalize;
      user-select: none;
    }
  }

  .trigger-open {
    width: 81px;
    border-top-left-radius: unset !important;
    border-top-right-radius: unset !important;
  }
}
</style>
