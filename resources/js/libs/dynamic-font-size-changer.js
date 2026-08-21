const changeFontSizeStatus = {
  "+": executeIncreaseOrDecreaseFontSize,
  "-": executeIncreaseOrDecreaseFontSize,
  "": executeSetDefaultFontSize
};

const executeIncreaseOrDecreaseMethod = {
  "+": increaseFontSize,
  "-": decreaseFontSize
};

const excludeFontSizeModificationClassList = ['font-increase-icon', 'font-default-icon', 'font-decrease-icon',
  'font-decrease-text', 'font-increase-text', 'font-default-text', 'font-setting-text'];

function increaseFontSize(prevFontSizeValue, fontSizeToAdd) {
  return prevFontSizeValue + fontSizeToAdd + 'px';
}

function decreaseFontSize(prevFontSizeValue, fontSizeToAdd) {
  return prevFontSizeValue - fontSizeToAdd + 'px';
}

function executeIncreaseOrDecreaseFontSize(element, status = "", fontSizeToAdd) {
  const prevFontSize = parseInt(window.getComputedStyle(element).getPropertyValue('font-size'));
  element.style.fontSize = executeIncreaseOrDecreaseMethod[`${status}`](prevFontSize, fontSizeToAdd);
}

function executeSetDefaultFontSize(element) {
  element.style.fontSize = "";
}

function processFontSizeModification(listOfElements, status, defaultFontSize) {
  const toAddFontSizeValue = defaultFontSize === undefined ? 1 : defaultFontSize;

  if (listOfElements?.length > 0) {
    for (const elemList of listOfElements) {
      for (const elem of elemList) {
        if (!excludeFontSizeModificationClassList.includes(elem.classList[0])) {
          changeFontSizeStatus[`${status}`](elem, status, Math.abs(toAddFontSizeValue));
        }
      }
    }
  }
}

export default function ChangeFontSize() {
  const aElements = document.getElementsByTagName("a");
  const pElements = document.getElementsByTagName("p");
  const h2Elements = document.getElementsByTagName("h2");
  const btnElements = document.getElementsByTagName("button");
  const spanElements = document.getElementsByTagName("span");

  return function(status, defaultFontSize) {
    if (changeFontSizeStatus[status]) return processFontSizeModification([aElements, pElements, h2Elements, spanElements, btnElements], status, defaultFontSize);
  };
}
