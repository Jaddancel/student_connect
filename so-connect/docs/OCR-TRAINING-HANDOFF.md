# OCR Improvement Handoff

Date: 2026-07-13

## Current OCR setup

- The OCR path is an inference-only FastAPI sidecar in [docker/ocr/app.py](/home/jad/projects/student_connect/so-connect/docker/ocr/app.py).
- It wraps PaddleOCR 2.9.1 with PaddlePaddle 2.6.2 from [docker/ocr/requirements.txt](/home/jad/projects/student_connect/so-connect/docker/ocr/requirements.txt).
- The sidecar is built and exposed from [compose.yaml](/home/jad/projects/student_connect/so-connect/compose.yaml).
- Laravel talks to it through [app/Services/OcrClient.php](/home/jad/projects/student_connect/so-connect/app/Services/OcrClient.php).
- The repo already supports zone-based OCR, signature crop extraction, and signature matching; there is no training loop or dataset pipeline in the repo.

## What is realistically improvable on an ASUS TUF Gaming A15 with 8GB VRAM

### High ROI without retraining

- Improve photo capture quality and input normalization.
- Tighten template geometry and crop placement.
- Use deskew, rotation correction, denoising, contrast normalization, and crop padding.
- Restrict OCR post-processing with regex and field-specific validation.

### Light fine-tuning that fits 8GB VRAM

- Fine-tune only the text recognizer on cropped line images from your own ID samples.
- Prefer small/mobile recognizers such as PP-OCRv6_tiny_rec, PP-OCRv6_small_rec, or PP-OCRv5_mobile_rec.
- Fine-tune detection only if text is being missed before recognition, and keep to tiny/mobile detector variants.
- Use batch size 1, mixed precision if available, and gradient accumulation if training on GPU.

### Auxiliary models worth considering

- Document orientation classification for rotated scans.
- Text-line orientation classification if slanted text is a common issue.
- Domain-specific signature verification if you want to improve the signature path separately from OCR.

## What is not a good fit for 8GB VRAM

- Training large server-scale OCR models from scratch.
- Training the full OCR pipeline end-to-end with large backbones.
- Moving to large document VLM-style models as the primary OCR engine.

## Practical recommendation for this codebase

1. First, improve preprocessing and template quality.
2. Next, fine-tune recognition on your own cropped ID text samples.
3. Add orientation classification only if rotated images are a real failure mode.
4. Keep deployment models small and export them for inference only.

## Relevant source references

- [docker/ocr/app.py](/home/jad/projects/student_connect/so-connect/docker/ocr/app.py)
- [docker/ocr/requirements.txt](/home/jad/projects/student_connect/so-connect/docker/ocr/requirements.txt)
- [compose.yaml](/home/jad/projects/student_connect/so-connect/compose.yaml)
- [app/Services/OcrClient.php](/home/jad/projects/student_connect/so-connect/app/Services/OcrClient.php)
- [docs/ocr-template-contract.md](/home/jad/projects/student_connect/so-connect/docs/ocr-template-contract.md)

## Notes for Claude Code

- Treat this OCR stack as an inference sidecar unless you add a separate training workspace.
- On 8GB VRAM, favor mobile/tiny PaddleOCR models and narrow-domain datasets.
- The best accuracy gains are likely to come from data quality, preprocessing, and field-specific fine-tuning rather than a full model rewrite.
