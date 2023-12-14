# Description
This module provides custom bundle classes for the following media bundles:
* Audio
* Document
* External Embed
* Image
* Remote Video
* Video

All Mukurtu CMS media types support cultural protocols.
>NOTE: For media types that access external resources, only the media entity will be protocol controlled. The external resource itself (e.g., a Vimeo video) will be accessible as configured on the remote host, independent of Mukurtu CMS configuration.

## Thumbnail Generation Requirements
For some bundles and/or for supported file types (e.g., A document entity with a PDF file) this module provides automatic thumbnail generation, provided the hosting system has the required tools installed and available.
* ffmpeg is used to generate video thumbnails. It is available for Debian/Ubuntu under the ffmpeg package.
* pdftoppm is used to generate document (PDF) thumbnails. It is available for Debian/Ubuntu under the poppler-utils package.
