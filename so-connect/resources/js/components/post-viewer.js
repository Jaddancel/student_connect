export function postViewer() {
    return {
        post: null,
        index: 0,
        imageFailed: false,
        videoFailed: false,
        previousOverflow: "",
        opener: null,

        get images() {
            return this.post?.images ?? [];
        },

        get currentImage() {
            return this.images[this.index] ?? "";
        },

        openPost(post, opener) {
            this.post = post;
            this.index = 0;
            this.imageFailed = false;
            this.videoFailed = false;
            this.opener = opener;
            this.previousOverflow = document.body.style.overflow;
            document.body.style.overflow = "hidden";
            this.$nextTick(() => this.$refs.postViewer.showModal());
        },

        closePost() {
            this.$refs.postViewer.querySelector("video")?.pause();
            this.$refs.postViewer.close();
            document.body.style.overflow = this.previousOverflow;
            this.post = null;
            this.opener?.focus({ preventScroll: true });
        },

        changeImage(direction) {
            if (this.images.length < 2) return;
            this.index = (this.index + direction + this.images.length) % this.images.length;
            this.imageFailed = false;
        },

        handleKey(event) {
            if (!this.post || !["ArrowLeft", "ArrowRight"].includes(event.key)) return;
            if (event.target.closest("video, input, textarea, select, [contenteditable]")) return;
            event.preventDefault();
            this.changeImage(event.key === "ArrowRight" ? 1 : -1);
        },
    };
}
