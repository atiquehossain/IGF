// PDFViewer.vue
<template>
  <div class="pdf-viewer-container">
    <div class="controls">
      <button @click="previousPage" :disabled="currentPage <= 1">Previous</button>
      <span>Page {{ currentPage }} of {{ totalPages }}</span>
      <button @click="nextPage" :disabled="currentPage >= totalPages">Next</button>
      <button @click="enterFullScreen" >Full Screen</button>
    </div>
    <div class="pdf-container" ref="container">
      <canvas ref="pdfCanvas"></canvas>
    </div>
  </div>
</template>

<script>
import * as pdfjsLib from 'pdfjs-dist';

pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
  'pdfjs-dist/build/pdf.worker.min.mjs',
  import.meta.url,
).toString();


export default {
  name: 'PDFViewer',
  props: {
    pdfUrl: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      pdfDoc: null,
      currentPage: 1,
      totalPages: 0,
      scale: 1,
      isLoading: false,
      pdfLink: null
    };
  },
  async mounted() {
      await this.loadPDF();
      await this.renderPage();

      // Add scroll event listener for page navigation
      this.$refs.container.addEventListener("scroll", this.changePageOnScroll);
  },
  beforeUnmount() {
    this.$refs.container?.removeEventListener("scroll", this.changePageOnScroll);
    this.pdfDoc?.destroy();
  },
  methods: {
    async loadPDF() {
      try {
        this.isLoading = true;
        // Load the PDF
        const loadingTask = pdfjsLib.getDocument(this.pdfUrl);
        this.pdfDoc = await loadingTask.promise;
        this.totalPages = this.pdfDoc.numPages;
      } catch {
        console.error('Unable to load the PDF document.');
      } finally {
        this.isLoading = false;
      }
    },
    async renderPage() {
      if (!this.pdfDoc) return;

      try {
        const page = await this.pdfDoc.getPage(this.currentPage);
      const canvas = this.$refs.pdfCanvas;
      const container = this.$refs.container;
      const context = canvas.getContext("2d");

      // Fit viewport to container width while maintaining aspect ratio
      const viewport = page.getViewport({ scale: 1 }); // Start with scale 1 for default size
      const scale = container.clientWidth / viewport.width; // Scale to fit width
      const scaledViewport = page.getViewport({ scale: scale });

      canvas.height = scaledViewport.height;
      canvas.width = scaledViewport.width;

      // Render PDF page
      const renderContext = {
        canvasContext: context,
        viewport: scaledViewport,
      };
      await page.render(renderContext).promise;

      // Disable right-click on the canvas
      canvas.addEventListener("contextmenu", function (event) {
        event.preventDefault();
      });
      } catch {
        console.error('Unable to render the PDF page.');
      }
    },
    changePageOnScroll(){
      // Calculate page based on scroll position
      const container = this.$refs.container;
      const pageHeight = container.scrollHeight / this.totalPages;
      const scrolledPage = Math.floor(container.scrollTop / pageHeight) + 1;

      if (scrolledPage !== this.currentPage && scrolledPage >= 1 && scrolledPage <= this.totalPages) {
        this.currentPage = scrolledPage;
        this.renderPage();
      }
    },
    enterFullScreen() {
      const container = this.$refs.container;
      if (container.requestFullscreen) {
        container.requestFullscreen();
      } else if (container.mozRequestFullScreen) {
        container.mozRequestFullScreen();
      } else if (container.webkitRequestFullscreen) {
        container.webkitRequestFullscreen();
      } else if (container.msRequestFullscreen) {
        container.msRequestFullscreen();
      }
    },
    previousPage() {
      if (this.currentPage > 1) {
        this.currentPage--;
      }
    },
    nextPage() {
      if (this.currentPage < this.totalPages) {
        this.currentPage++;
      }
    }
  },
  watch: {
    currentPage() {
      this.renderPage();
    },
    scale() {
      this.renderPage();
    }
  }
};
</script>

<style scoped>
.pdf-viewer-container {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1rem;
  max-width: 100%;
  margin: 0 auto;
}

.controls {
  display: flex;
  gap: 1rem;
  align-items: center;
  justify-content: center;
}

.pdf-container {
  overflow-x: auto;
  overflow-y: auto;
  max-height: 80vh;
  border: 1px solid #ccc;
}

.page-slider {
  width: 200px;
}

canvas {
  display: block;
  margin: 0 auto;
}

button {
  padding: 0.5rem 1rem;
  cursor: pointer;
}

button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
</style>
