const HEIC_EXTENSION = /\.(heic|heif)$/i;

export async function heicToJpeg(blob) {
    const heic2any = (await import("heic2any")).default;
    const converted = await heic2any({
        blob,
        toType: "image/jpeg",
        quality: 0.9,
    });

    return Array.isArray(converted) ? converted[0] : converted;
}

export function formUploadPreview({ multiple = false, maxFiles = 5 } = {}) {
    return {
        previews: [],
        previewUrls: [],
        error: "",
        previewSequence: 0,

        clearPreviews() {
            this.previewUrls.forEach((url) => URL.revokeObjectURL(url));
            this.previewUrls = [];
            this.previews = [];
        },

        async updatePreview(event) {
            const files = Array.from(event.target.files || []);
            const sequence = ++this.previewSequence;
            this.clearPreviews();
            this.error = "";

            if (multiple && files.length > maxFiles) {
                this.error = `Choose up to ${maxFiles} images.`;
                event.target.value = "";
                return;
            }

            const previews = await Promise.all(
                files
                    .filter(
                        (file) =>
                            file.type.startsWith("image/") ||
                            HEIC_EXTENSION.test(file.name),
                    )
                    .map(async (file) => {
                        try {
                            const previewBlob = HEIC_EXTENSION.test(file.name)
                                ? await heicToJpeg(file)
                                : file;

                            return URL.createObjectURL(previewBlob);
                        } catch (error) {
                            console.error("Unable to preview selected image.", error);
                            this.error =
                                "The image was selected, but its preview could not be generated.";
                            return null;
                        }
                    }),
            );

            const urls = previews.filter(Boolean);
            if (sequence !== this.previewSequence) {
                urls.forEach((url) => URL.revokeObjectURL(url));
                return;
            }

            this.previewUrls = urls;
            this.previews = urls;
        },

        destroy() {
            this.clearPreviews();
        },
    };
}

export function mediaPreview({ src, label, isHeic = false }) {
    return {
        metadataOpen: false,
        previewUrl: src,
        ownedPreviewUrl: null,
        previewError: "",

        async init() {
            if (!isHeic) {
                return;
            }

            try {
                const response = await fetch(src);
                if (!response.ok) {
                    throw new Error(`Unable to load image (${response.status}).`);
                }

                const converted = await heicToJpeg(await response.blob());
                this.ownedPreviewUrl = URL.createObjectURL(converted);
                this.previewUrl = this.ownedPreviewUrl;
            } catch (error) {
                console.error("Unable to preview submitted HEIC image.", error);
                this.previewError = "Preview unavailable for this HEIC image.";
            }
        },

        openPreview() {
            if (this.previewUrl) {
                this.$store.lightbox.show(this.previewUrl, label);
            }
        },

        destroy() {
            if (this.ownedPreviewUrl) {
                URL.revokeObjectURL(this.ownedPreviewUrl);
            }
        },
    };
}
