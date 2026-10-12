// Feature: image-viewing — every page that previews applicant uploads must use
// the shared ImageViewer instead of its own ad-hoc preview modal.

import { describe, it, expect } from 'vitest'
import * as fs from 'fs'
import * as path from 'path'

const JS_ROOT = path.resolve(__dirname, '../..')

/** Pages/components that render the shared viewer for applicant uploads. */
const VIEWER_PAGES = [
    'Pages/Applications/Evaluator.vue',
    'Pages/Applications/Index.vue',
    'Pages/Applications/Interviewer.vue',
    'Pages/Applications/Records.vue',
    'Pages/Dashboard/Admin.vue',
    'Pages/Dashboard/Applicant.vue',
    'Pages/Dashboard/Evaluator.vue',
    'Pages/Dashboard/Interviewer.vue',
    'Pages/Dashboard/Records.vue',
    'Pages/Modal/ApplicationReviewModal.vue',
    'Pages/UserManagement/EditUser.vue',
]

/** Components that only forward the click to a parent that owns the viewer. */
const IMAGE_EMITTERS = ['Pages/Applications/UserDetailsModal.vue']

const read = (relative: string) => fs.readFileSync(path.join(JS_ROOT, relative), 'utf-8')

describe('Image viewer integration', () => {
    it('the shared ImageViewer component exists', () => {
        expect(fs.existsSync(path.join(JS_ROOT, 'Components/ImageViewer.vue'))).toBe(true)
    })

    for (const page of VIEWER_PAGES) {
        describe(page, () => {
            const source = read(page)

            it('uses the shared ImageViewer rather than a bespoke preview modal', () => {
                expect(source).toContain('Components/ImageViewer.vue')
                expect(source).toMatch(/<ImageViewer[\s>]/)

                // No leftover ad-hoc preview overlays.
                expect(source).not.toMatch(/class="[^"]*doc-preview-overlay/)
                expect(source).not.toMatch(/class="[^"]*doc-preview-modal/)
                // The old markup rendered the full-bleed preview inline.
                expect(source).not.toMatch(/alt="Preview"/)
                expect(source).not.toMatch(/alt="Document Preview"/)
            })

            it('binds the viewer to the existing open/close state', () => {
                expect(source).toContain(':show="showImageModal"')
                expect(source).toMatch(/@close="closeImageModal"/)
            })

            it('passes a meaningful alt text to the viewer', () => {
                expect(source).toContain('const previewAlt')
                expect(source).toContain(':alt="previewAlt"')
            })

            it('keeps the existing openImageModal click behaviour', () => {
                expect(source).toContain('const openImageModal')
                expect(source).toContain('const closeImageModal')
            })
        })
    }

    for (const emitter of IMAGE_EMITTERS) {
        it(`${emitter} still emits open-image (with a label) for its parent viewer`, () => {
            const source = read(emitter)
            expect(source).toContain('"open-image"')
            expect(source).toMatch(/emit\('open-image', file/)
            // The emitter must not own its own preview modal.
            expect(source).not.toMatch(/<ImageViewer[\s>]/)
        })
    }

    it('no page hand-rolls a full-screen image overlay any more', () => {
        for (const page of VIEWER_PAGES) {
            const source = read(page)
            // The only full-screen overlay allowed is the shared viewer's own
            // component, so no page should pair a dimmed backdrop with a
            // stand-alone <img> preview any more.
            const hasBackdrop = /bg-black(\/\d+)?\b/.test(source) && /showImageModal/.test(source)
            expect(
                hasBackdrop && /<img[^>]*:src="preview(Image|Src)"/.test(source),
                `${page} still renders its own preview <img>`
            ).toBe(false)
        }
    })
})
