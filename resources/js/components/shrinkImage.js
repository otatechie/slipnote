// Phone photos arrive at 3–6 MB. Scaled to a size handwriting still reads at
// and re-saved, they're a tenth of that: less data for whoever uploads or
// downloads them, and re-encoding drops EXIF, so no hidden GPS location.
// JPEG only: PNGs are mostly screenshots, whose text JPEG would blur. The cap
// is on pixels, not the long edge, so a tall scrolling screenshot or a
// panorama keeps a readable width (a 12 MP photo comes out 2309×1732).
const MAX_PIXELS = 4_000_000
const QUALITY = 0.8
const MIN_BYTES = 600 * 1024

/** The file to upload in place of `file`: a smaller JPEG, or `file` itself when shrinking doesn't pay. */
export async function shrinkImage(file) {
    if (file.type !== 'image/jpeg' || file.size < MIN_BYTES) {
        return file
    }

    const url = URL.createObjectURL(file)
    try {
        // An <img> is drawn the way up its EXIF says in every current browser;
        // createImageBitmap's imageOrientation option is honoured in fewer, and
        // dropping EXIF from a sideways bitmap would leave a page nobody can read.
        const img = new Image()
        img.src = url
        await img.decode()

        const scale = Math.min(1, Math.sqrt(MAX_PIXELS / (img.naturalWidth * img.naturalHeight)))
        const canvas = document.createElement('canvas')
        canvas.width = Math.round(img.naturalWidth * scale)
        canvas.height = Math.round(img.naturalHeight * scale)

        const ctx = canvas.getContext('2d')
        ctx.imageSmoothingQuality = 'high'
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height)

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', QUALITY))
        if (!blob || blob.size > file.size * 0.8) {
            return file
        }

        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', {
            type: 'image/jpeg',
            lastModified: file.lastModified,
        })
    } catch {
        return file
    } finally {
        URL.revokeObjectURL(url)
    }
}
