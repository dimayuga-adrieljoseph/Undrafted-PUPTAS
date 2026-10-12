// Feature: image-viewing — reusable document/image lightbox (zoom, pan, fit).

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ImageViewer from '@/Components/ImageViewer.vue'

/** Stub the jsdom layout engine so fit-to-viewport math is deterministic. */
const VIEWPORT = { width: 1000, height: 700 }

type Wrapper = ReturnType<typeof mountViewer>

function mountViewer(props: Record<string, unknown> = {}) {
    return mount(ImageViewer, {
        props: { show: true, src: '/storage/documents/report-card.png', ...props },
        attachTo: document.body,
        // The global test setup stubs document.querySelector, which breaks
        // <Teleport to="body">. Rendering in place keeps the DOM queryable.
        global: { stubs: { teleport: true } },
    })
}

/** Dispatch a DOM event, adding the extras jsdom's constructors do not accept. */
function fire(
    target: Element,
    type: string,
    init: Record<string, number> = {},
    Base: typeof MouseEvent = MouseEvent
) {
    const event = new Base(type, {
        bubbles: true,
        cancelable: true,
        clientX: init.clientX ?? 0,
        clientY: init.clientY ?? 0,
        button: init.button ?? 0,
    } as never)
    for (const [key, value] of Object.entries(init)) {
        if (key === 'clientX' || key === 'clientY' || key === 'button') continue
        Object.defineProperty(event, key, { value, configurable: true })
    }
    target.dispatchEvent(event)
    return event
}

/** Simulate a successful image decode with the given natural dimensions. */
async function loadImage(wrapper: Wrapper, naturalWidth: number, naturalHeight: number) {
    const img = wrapper.get('[data-test="image-viewer-image"]').element as HTMLImageElement
    Object.defineProperty(img, 'naturalWidth', { value: naturalWidth, configurable: true })
    Object.defineProperty(img, 'naturalHeight', { value: naturalHeight, configurable: true })
    fire(img, 'load')
    await flushPromises()
}

/** Read the applied CSS transform of the image. */
function transformOf(wrapper: Wrapper) {
    const style = wrapper.get('[data-test="image-viewer-image"]').attributes('style') ?? ''
    const match = /translate\(([-\d.]+)px,\s*([-\d.]+)px\) scale\(([-\d.]+)\)/.exec(style)
    if (!match) throw new Error(`Could not parse transform from: ${style}`)
    return { x: parseFloat(match[1]), y: parseFloat(match[2]), scale: parseFloat(match[3]) }
}

function zoomLevel(wrapper: Wrapper) {
    return wrapper.get('[data-test="image-viewer-zoom-level"]').text()
}

/** The raw CSS transform applied to the image. */
function transformCss(wrapper: Wrapper) {
    const style = wrapper.get('[data-test="image-viewer-image"]').attributes('style') ?? ''
    const match = /(?:^|;)\s*transform:\s*([^;]+)/.exec(style)
    if (!match) throw new Error(`Could not find a transform in: ${style}`)
    return match[1].trim()
}

/** Give the canvas a real bounding box so pointer coordinates are meaningful. */
function stubRect(wrapper: Wrapper) {
    vi.spyOn(wrapper.get('[data-test="image-viewer-canvas"]').element, 'getBoundingClientRect')
        .mockReturnValue({
            left: 0,
            top: 0,
            width: VIEWPORT.width,
            height: VIEWPORT.height,
        } as DOMRect)
}

/** Press a toolbar control. */
const clickControl = async (wrapper: Wrapper, testId: string) => {
    fire(wrapper.get(`[data-test="${testId}"]`).element, 'click')
    await flushPromises()
}

beforeEach(() => {
    // jsdom reports 0 for every layout measurement; give the canvas a real box.
    Object.defineProperty(HTMLElement.prototype, 'clientWidth', {
        configurable: true,
        get() {
            return (this as HTMLElement).dataset?.test === 'image-viewer-canvas' ? VIEWPORT.width : 0
        },
    })
    Object.defineProperty(HTMLElement.prototype, 'clientHeight', {
        configurable: true,
        get() {
            return (this as HTMLElement).dataset?.test === 'image-viewer-canvas' ? VIEWPORT.height : 0
        },
    })
    // Pointer capture is not implemented in jsdom.
    const proto = HTMLElement.prototype as unknown as Record<string, unknown>
    delete proto.setPointerCapture
    delete proto.hasPointerCapture
    delete proto.releasePointerCapture

    // jsdom never fetches images and reports them as already complete, which
    // would make the viewer jump straight to its error state. Simulate an
    // in-flight request so the loading state is observable.
    Object.defineProperty(HTMLImageElement.prototype, 'complete', {
        configurable: true,
        get: () => false,
    })
})

afterEach(() => {
    document.body.innerHTML = ''
    document.body.style.overflow = ''
    vi.restoreAllMocks()
})

describe('ImageViewer — opening and fit-to-viewport', () => {
    it('renders a modal dialog with a dimmed backdrop when shown', () => {
        const wrapper = mountViewer()
        const dialog = wrapper.get('[role="dialog"]').element as HTMLElement
        expect(dialog).not.toBeNull()
        expect(dialog.getAttribute('aria-modal')).toBe('true')
        expect(dialog.className).toContain('bg-black/90')
        wrapper.unmount()
    })

    it('renders nothing when show is false', () => {
        const wrapper = mountViewer({ show: false })
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        wrapper.unmount()
    })

    it('shows a loading state before the image decodes', async () => {
        const wrapper = mountViewer()
        await flushPromises()
        expect(wrapper.find('[data-test="image-viewer-loading"]').exists()).toBe(true)
        expect(wrapper.find('[data-test="image-viewer-image"]').exists()).toBe(true)
        wrapper.unmount()
    })

    it('passes the alt text through to the image and the dialog label', async () => {
        const wrapper = mountViewer({ alt: 'Grade 12 Report Card' })
        await loadImage(wrapper, 800, 600)
        expect(wrapper.get('[data-test="image-viewer-image"]').attributes('alt')).toBe('Grade 12 Report Card')
        const dialog = wrapper.get('[role="dialog"]').element as HTMLElement
        expect(dialog.getAttribute('aria-label')).toBe('Grade 12 Report Card')
        wrapper.unmount()
    })

    it('never modifies the provided image URL', async () => {
        const src = '/files/123/preview?signature=abc'
        const wrapper = mountViewer({ src })
        await loadImage(wrapper, 400, 300)
        expect(wrapper.get('[data-test="image-viewer-image"]').attributes('src')).toBe(src)
        wrapper.unmount()
    })

    it('scales a large landscape image down so it fits the viewport width', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000) // 4:1, width-constrained
        // 1000 / 4000 = 0.25 ; height at 0.25 = 250 <= 700
        expect(transformOf(wrapper).scale).toBeCloseTo(0.25, 5)
        expect(wrapper.get('[data-test="image-viewer-image"]').attributes('style')).toContain('width: 4000px')
        wrapper.unmount()
    })

    it('scales a very tall portrait document down so it fits the viewport height', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 1200, 6000) // 1:5, height-constrained
        expect(transformOf(wrapper).scale).toBeCloseTo(700 / 6000, 5)
        wrapper.unmount()
    })

    it('scales a very wide document down so it fits the viewport width', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 9000, 500) // 18:1
        expect(transformOf(wrapper).scale).toBeCloseTo(1000 / 9000, 5)
        wrapper.unmount()
    })

    it('does not upscale a small image beyond its natural size', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 200, 150)
        expect(transformOf(wrapper).scale).toBe(1)
        expect(zoomLevel(wrapper)).toBe('100%')
        wrapper.unmount()
    })

    it('preserves the aspect ratio (no stretching)', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 3000, 2000)
        const style = wrapper.get('[data-test="image-viewer-image"]').attributes('style') ?? ''
        // Explicit width/height equal the natural size, so the ratio is never distorted.
        expect(style).toContain('width: 3000px')
        expect(style).toContain('height: 2000px')
        wrapper.unmount()
    })

    it('starts centred with no pan offset', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)
        const t = transformOf(wrapper)
        expect(t.x).toBe(0)
        expect(t.y).toBe(0)
        wrapper.unmount()
    })
})


describe('ImageViewer — initial fit and centring', () => {
    /** Image shapes that must all open fully visible and dead centre. */
    const SHAPES = [
        { name: 'small image', width: 200, height: 150 },
        { name: 'large image', width: 4000, height: 3000 },
        { name: 'portrait image', width: 1200, height: 3600 },
        { name: 'landscape image', width: 3600, height: 1200 },
        { name: 'very tall report card', width: 1000, height: 9000 },
        { name: 'very wide image', width: 9000, height: 500 },
    ]

    for (const shape of SHAPES) {
        it(`fits a ${shape.name} to the available area without cropping it`, async () => {
            const wrapper = mountViewer()
            await loadImage(wrapper, shape.width, shape.height)

            const t = transformOf(wrapper)
            const expected = Math.min(
                VIEWPORT.width / shape.width,
                VIEWPORT.height / shape.height,
                1
            )
            expect(t.scale).toBeCloseTo(expected, 5)

            // The scaled document never overflows the viewer area.
            expect(shape.width * t.scale).toBeLessThanOrEqual(VIEWPORT.width + 0.5)
            expect(shape.height * t.scale).toBeLessThanOrEqual(VIEWPORT.height + 0.5)
            wrapper.unmount()
        })

        it(`centres a ${shape.name} on both axes`, async () => {
            const wrapper = mountViewer()
            await loadImage(wrapper, shape.width, shape.height)

            const t = transformOf(wrapper)
            expect(t.x).toBe(0)
            expect(t.y).toBe(0)
            // The box is pulled back by half of its own size, so its centre —
            // not its top-left corner — sits on the canvas anchor.
            expect(transformCss(wrapper).startsWith('translate(-50%, -50%)')).toBe(true)
            wrapper.unmount()
        })
    }

    it('anchors the image box on the canvas centre', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)

        const img = wrapper.get('[data-test="image-viewer-image"]')
        expect(img.classes()).toContain('left-1/2')
        expect(img.classes()).toContain('top-1/2')
        wrapper.unmount()
    })

    it('re-centres the document on reset after zooming and panning', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 1200, 6000)
        stubRect(wrapper)

        await clickControl(wrapper, 'image-viewer-zoom-in')
        const el = wrapper.get('[data-test="image-viewer-canvas"]').element
        fire(el, 'pointerdown', { pointerId: 1, button: 0, clientX: 500, clientY: 350 })
        fire(el, 'pointermove', { pointerId: 1, clientX: 460, clientY: 300 })
        fire(el, 'pointerup', { pointerId: 1, clientX: 460, clientY: 300 })
        await flushPromises()
        expect(transformOf(wrapper).y).not.toBe(0)

        await clickControl(wrapper, 'image-viewer-reset')
        const t = transformOf(wrapper)
        expect(t.scale).toBeCloseTo(700 / 6000, 5)
        expect(t.x).toBe(0)
        expect(t.y).toBe(0)
        wrapper.unmount()
    })
})



describe('ImageViewer — zoom controls', () => {
    it('increases the scale on zoom in and shows the relative zoom level', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000) // fit = 0.25
        expect(zoomLevel(wrapper)).toBe('100%')

        await clickControl(wrapper, 'image-viewer-zoom-in')
        expect(transformOf(wrapper).scale).toBeCloseTo(0.25 * 1.25, 5)
        expect(zoomLevel(wrapper)).toBe('125%')

        await clickControl(wrapper, 'image-viewer-zoom-in')
        expect(transformOf(wrapper).scale).toBeCloseTo(0.25 * 1.25 * 1.25, 5)
        expect(zoomLevel(wrapper)).toBe('156%')
        wrapper.unmount()
    })

    it('decreases the scale on zoom out and never goes below the default fit scale', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)
        await clickControl(wrapper, 'image-viewer-zoom-in')

        await clickControl(wrapper, 'image-viewer-zoom-out')
        expect(transformOf(wrapper).scale).toBeCloseTo(0.25, 5)
        expect(zoomLevel(wrapper)).toBe('100%')

        // Already at the minimum — the scale must not drop further.
        await clickControl(wrapper, 'image-viewer-zoom-out')
        expect(transformOf(wrapper).scale).toBeCloseTo(0.25, 5)
        expect(zoomLevel(wrapper)).toBe('100%')
        wrapper.unmount()
    })

    it('disables the zoom-out control when already at the minimum scale', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)
        expect(wrapper.get('[data-test="image-viewer-zoom-out"]').attributes('disabled')).toBeDefined()

        await clickControl(wrapper, 'image-viewer-zoom-in')
        expect(wrapper.get('[data-test="image-viewer-zoom-out"]').attributes('disabled')).toBeUndefined()
        wrapper.unmount()
    })

    it('caps zooming at a sensible maximum level', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000) // fit = 0.25, max = 0.25 * 8
        for (let i = 0; i < 25; i += 1) {
            await clickControl(wrapper, 'image-viewer-zoom-in')
        }
        expect(transformOf(wrapper).scale).toBeCloseTo(2, 5)
        expect(zoomLevel(wrapper)).toBe('800%')
        wrapper.unmount()
    })

    it('bounds the zoom for a very large scan instead of scaling without limit', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 8000, 10000) // fit = 0.07
        for (let i = 0; i < 40; i += 1) {
            await clickControl(wrapper, 'image-viewer-zoom-in')
        }
        expect(transformOf(wrapper).scale).toBeCloseTo(0.07 * 8, 4)
        wrapper.unmount()
    })

    it('returns to the fit-to-viewport state on reset', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)
        for (let i = 0; i < 4; i += 1) {
            await clickControl(wrapper, 'image-viewer-zoom-in')
        }
        expect(transformOf(wrapper).scale).not.toBeCloseTo(0.25, 5)

        await clickControl(wrapper, 'image-viewer-reset')
        const t = transformOf(wrapper)
        expect(t.scale).toBeCloseTo(0.25, 5)
        expect(t.x).toBe(0)
        expect(t.y).toBe(0)
        expect(zoomLevel(wrapper)).toBe('100%')
        wrapper.unmount()
    })

    it('zooms toward the cursor instead of always the centre', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)
        stubRect(wrapper)

        // Wheel up with the cursor to the right of centre.
        const cursor = { clientX: 900, clientY: 350 }
        const before = transformOf(wrapper)
        // Image point (in natural pixels) currently under the cursor.
        const anchoredBefore = {
            x: (cursor.clientX - VIEWPORT.width / 2 - before.x) / before.scale,
            y: (cursor.clientY - VIEWPORT.height / 2 - before.y) / before.scale,
        }

        fire(wrapper.get('[data-test="image-viewer-canvas"]').element, 'wheel', {
            deltaY: -400,
            ...cursor,
        })
        await flushPromises()

        const after = transformOf(wrapper)
        expect(after.scale).toBeGreaterThan(before.scale)
        // The same image point must still sit under the cursor.
        const anchoredAfter = {
            x: (cursor.clientX - VIEWPORT.width / 2 - after.x) / after.scale,
            y: (cursor.clientY - VIEWPORT.height / 2 - after.y) / after.scale,
        }
        expect(anchoredAfter.x).toBeCloseTo(anchoredBefore.x, 3)
        expect(anchoredAfter.y).toBeCloseTo(anchoredBefore.y, 3)
        // Zooming in around a right-hand cursor pushes the image left.
        expect(after.x).toBeLessThan(0)
        wrapper.unmount()
    })

    it('toggles zoom on double click and back on the second double click', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)
        stubRect(wrapper)
        const el = wrapper.get('[data-test="image-viewer-canvas"]').element

        fire(el, 'dblclick', { clientX: 500, clientY: 350 })
        await flushPromises()
        expect(transformOf(wrapper).scale).toBeGreaterThan(0.25)

        fire(el, 'dblclick', { clientX: 500, clientY: 350 })
        await flushPromises()
        expect(transformOf(wrapper).scale).toBeCloseTo(0.25, 5)
        wrapper.unmount()
    })
})


describe('ImageViewer — panning while zoomed', () => {
    /** Mounted + zoomed in, with a real canvas box so pointer maths works. */
    async function zoomedWrapper() {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)
        stubRect(wrapper)
        await clickControl(wrapper, 'image-viewer-zoom-in')
        return wrapper
    }

    const drag = async (wrapper: Wrapper, from: Record<string, number>, to: Record<string, number>) => {
        const el = wrapper.get('[data-test="image-viewer-canvas"]').element
        fire(el, 'pointerdown', { pointerId: 1, button: 0, ...from })
        fire(el, 'pointermove', { pointerId: 1, ...to })
        await flushPromises()
        return el
    }

    it('moves the image when dragging with the mouse', async () => {
        const wrapper = await zoomedWrapper()
        // At this zoom the image is 1250x312 in a 1000x700 canvas, so it can
        // only be panned horizontally.
        const el = await drag(wrapper, { clientX: 500, clientY: 350 }, { clientX: 560, clientY: 390 })

        const t = transformOf(wrapper)
        expect(t.x).toBeCloseTo(60, 3)
        expect(t.y).toBe(0)

        fire(el, 'pointerup', { pointerId: 1, clientX: 560, clientY: 390 })
        wrapper.unmount()
    })

    it('pans on both axes once the image overflows the canvas in both directions', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 3000, 3000) // square, so the same rule applies to X and Y
        stubRect(wrapper)
        await clickControl(wrapper, 'image-viewer-zoom-in')
        await clickControl(wrapper, 'image-viewer-zoom-in')

        const el = await drag(wrapper, { clientX: 500, clientY: 350 }, { clientX: 530, clientY: 390 })
        const t = transformOf(wrapper)
        expect(t.x).toBeCloseTo(30, 3)
        expect(t.y).toBeCloseTo(40, 3)

        fire(el, 'pointerup', { pointerId: 1, clientX: 530, clientY: 390 })
        wrapper.unmount()
    })

    it('supports single-finger touch dragging', async () => {
        const wrapper = await zoomedWrapper()
        const el = await drag(wrapper, { clientX: 500, clientY: 350 }, { clientX: 460, clientY: 350 })
        expect(transformOf(wrapper).x).toBeCloseTo(-40, 3)

        fire(el, 'pointerup', { pointerId: 1, clientX: 460, clientY: 350 })
        wrapper.unmount()
    })

    it('zooms with a two-finger pinch', async () => {
        const wrapper = await zoomedWrapper()
        const el = wrapper.get('[data-test="image-viewer-canvas"]').element
        const before = transformOf(wrapper).scale

        fire(el, 'pointerdown', { pointerId: 1, button: 0, clientX: 400, clientY: 350 })
        fire(el, 'pointerdown', { pointerId: 2, button: 0, clientX: 600, clientY: 350 })
        // Spread the fingers apart => zoom in.
        fire(el, 'pointermove', { pointerId: 2, clientX: 800, clientY: 350 })
        await flushPromises()

        expect(transformOf(wrapper).scale).toBeGreaterThan(before)
        wrapper.unmount()
    })

    it('never pans beyond the image edges', async () => {
        const wrapper = await zoomedWrapper()
        const el = await drag(wrapper, { clientX: 500, clientY: 350 }, { clientX: 5000, clientY: 350 })

        const t = transformOf(wrapper)
        const limit = (4000 * t.scale - VIEWPORT.width) / 2
        expect(t.x).toBeCloseTo(limit, 3)

        fire(el, 'pointerup', { pointerId: 1, clientX: 5000, clientY: 350 })
        wrapper.unmount()
    })

    it('does not pan at the default fit scale (whole image stays visible)', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)
        stubRect(wrapper)
        const el = await drag(wrapper, { clientX: 500, clientY: 350 }, { clientX: 700, clientY: 350 })

        expect(transformOf(wrapper).x).toBe(0)
        expect(transformOf(wrapper).y).toBe(0)

        fire(el, 'pointerup', { pointerId: 1, clientX: 700, clientY: 350 })
        wrapper.unmount()
    })

    it('does not start a pan from the dimmed backdrop', async () => {
        const wrapper = await zoomedWrapper()
        // (20,20) is outside the scaled image.
        await drag(wrapper, { clientX: 20, clientY: 20 }, { clientX: 300, clientY: 300 })
        const t = transformOf(wrapper)
        expect(t.x).toBe(0)
        expect(t.y).toBe(0)
        wrapper.unmount()
    })
})


describe('ImageViewer — closing', () => {
    it('emits close when the X button is clicked', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 800, 600)
        await clickControl(wrapper, 'image-viewer-close')
        expect(wrapper.emitted('close')).toHaveLength(1)
        wrapper.unmount()
    })

    it('emits close on the Escape key', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 800, 600)
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
        await flushPromises()
        expect(wrapper.emitted('close')).toHaveLength(1)
        wrapper.unmount()
    })

    it('emits close when clicking outside the image', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 800, 600) // scale 1, centred
        stubRect(wrapper)
        // The top-left corner is well outside the 800x600 image.
        fire(wrapper.get('[data-test="image-viewer-canvas"]').element, 'click', { clientX: 20, clientY: 20 })
        await flushPromises()
        expect(wrapper.emitted('close')).toHaveLength(1)
        wrapper.unmount()
    })

    it('does not close when clicking on the image itself', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 800, 600)
        stubRect(wrapper)
        fire(wrapper.get('[data-test="image-viewer-canvas"]').element, 'click', { clientX: 500, clientY: 350 })
        await flushPromises()
        expect(wrapper.emitted('close')).toBeUndefined()
        wrapper.unmount()
    })

    it('does not close when a pan drag ends over the backdrop', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)
        stubRect(wrapper)
        await clickControl(wrapper, 'image-viewer-zoom-in')

        const el = wrapper.get('[data-test="image-viewer-canvas"]').element
        fire(el, 'pointerdown', { pointerId: 1, button: 0, clientX: 500, clientY: 350 })
        fire(el, 'pointermove', { pointerId: 1, clientX: 700, clientY: 350 })
        fire(el, 'pointerup', { pointerId: 1, clientX: 700, clientY: 350 })
        fire(el, 'click', { clientX: 20, clientY: 20 })
        await flushPromises()

        expect(wrapper.emitted('close')).toBeUndefined()
        wrapper.unmount()
    })

    it('can opt out of closing via the backdrop', async () => {
        const wrapper = mountViewer({ closeOnBackdrop: false })
        await loadImage(wrapper, 800, 600)
        stubRect(wrapper)
        fire(wrapper.get('[data-test="image-viewer-canvas"]').element, 'click', { clientX: 20, clientY: 20 })
        await flushPromises()
        expect(wrapper.emitted('close')).toBeUndefined()
        wrapper.unmount()
    })

    it('locks body scrolling while open and restores it on close', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 800, 600)
        expect(document.body.style.overflow).toBe('hidden')

        await wrapper.setProps({ show: false })
        await flushPromises()
        expect(document.body.style.overflow).not.toBe('hidden')
        wrapper.unmount()
    })

    it('restores focus to the element that opened it', async () => {
        const trigger = document.createElement('button')
        trigger.textContent = 'open'
        document.body.appendChild(trigger)
        trigger.focus()

        const wrapper = mount(ImageViewer, {
            props: { show: false, src: '/doc.png' },
            attachTo: document.body,
            global: { stubs: { teleport: true } },
        })
        await wrapper.setProps({ show: true })
        await loadImage(wrapper, 800, 600)
        await flushPromises()

        // Focus moved into the dialog...
        expect(document.activeElement).toBe(wrapper.get('[data-test="image-viewer-close"]').element)

        // ...and comes back out again.
        await wrapper.setProps({ show: false })
        await flushPromises()
        expect(document.activeElement).toBe(trigger)
        wrapper.unmount()
    })

    it('resets the zoom state when reopened for another document', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)
        await clickControl(wrapper, 'image-viewer-zoom-in')
        expect(transformOf(wrapper).scale).not.toBeCloseTo(0.25, 5)

        await wrapper.setProps({ show: false })
        await wrapper.setProps({ show: true, src: '/storage/documents/other.png' })
        await loadImage(wrapper, 4000, 1000)

        const t = transformOf(wrapper)
        expect(t.scale).toBeCloseTo(0.25, 5)
        expect(t.x).toBe(0)
        expect(t.y).toBe(0)
        wrapper.unmount()
    })
})


describe('ImageViewer — keyboard shortcuts', () => {
    const press = async (key: string, init: Record<string, unknown> = {}) => {
        document.dispatchEvent(new KeyboardEvent('keydown', { key, ...init }))
        await flushPromises()
    }

    it('zooms in with + and =', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)
        await press('+')
        expect(transformOf(wrapper).scale).toBeCloseTo(0.3125, 5)
        await press('=')
        expect(transformOf(wrapper).scale).toBeCloseTo(0.390625, 5)
        wrapper.unmount()
    })

    it('zooms out with -', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)
        await press('+')
        await press('-')
        expect(transformOf(wrapper).scale).toBeCloseTo(0.25, 5)
        wrapper.unmount()
    })

    it('resets the zoom with 0', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)
        await press('+')
        await press('+')
        expect(transformOf(wrapper).scale).not.toBeCloseTo(0.25, 5)
        await press('0')
        expect(transformOf(wrapper).scale).toBeCloseTo(0.25, 5)
        wrapper.unmount()
    })

    it('ignores browser zoom shortcuts so ctrl+0 still works', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)
        await press('+', { ctrlKey: true })
        expect(transformOf(wrapper).scale).toBeCloseTo(0.25, 5)
        wrapper.unmount()
    })

    it('does nothing while hidden', async () => {
        const wrapper = mountViewer({ show: false })
        await press('+')
        expect(wrapper.emitted('close')).toBeUndefined()
        wrapper.unmount()
    })
})

describe('ImageViewer — PUPTAS visual language', () => {
    it('uses the PUPTAS modal header: maroon bar, document title, translucent close', async () => {
        const wrapper = mountViewer({ alt: 'Grade 12 Report Card' })
        await loadImage(wrapper, 800, 600)

        const header = wrapper.get('header')
        expect(header.classes()).toContain('bg-[#9E122C]')
        expect(header.text()).toContain('Grade 12 Report Card')

        const close = wrapper.get('[data-test="image-viewer-close"]')
        expect(close.classes()).toContain('bg-white/20')
        expect(close.classes()).toContain('rounded-full')
        expect(close.classes()).toContain('focus-visible:ring-[#FBCB77]')
        wrapper.unmount()
    })

    it('renders the toolbar as a PUPTAS surface instead of viewer chrome', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 800, 600)

        const toolbar = wrapper.get('[data-test="image-viewer-toolbar"]')
        for (const cls of [
            'bg-white',
            'dark:bg-gray-800',
            'border-gray-200',
            'dark:border-gray-700',
            'shadow-2xl',
            'rounded-full',
        ]) {
            expect(toolbar.classes()).toContain(cls)
        }
        wrapper.unmount()
    })

    it('gives every toolbar control the PUPTAS focus ring and disabled state', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 800, 600)

        for (const id of ['image-viewer-zoom-in', 'image-viewer-zoom-out', 'image-viewer-reset']) {
            const classes = wrapper.get(`[data-test="${id}"]`).classes()
            expect(classes).toContain('focus-visible:ring-[#9E122C]')
            expect(classes).toContain('dark:focus-visible:ring-[#FBCB77]')
            expect(classes).toContain('disabled:opacity-40')
        }
        wrapper.unmount()
    })

    it('styles the loading state like the PUPTAS loading card', async () => {
        const wrapper = mountViewer()
        await flushPromises()

        const state = wrapper.get('[data-test="image-viewer-loading"]')
        expect(state.get('div').classes()).toContain('bg-white')
        expect(state.get('div').classes()).toContain('shadow-2xl')
        expect(state.get('svg').classes()).toContain('text-[#9E122C]')
        wrapper.unmount()
    })

    it('styles the error state as a PUPTAS card', async () => {
        const wrapper = mountViewer()
        fire(wrapper.get('[data-test="image-viewer-image"]').element, 'error')
        await flushPromises()

        const card = wrapper.get('[data-test="image-viewer-error"] > div')
        expect(card.classes()).toContain('rounded-2xl')
        expect(card.classes()).toContain('bg-white')
        expect(card.classes()).toContain('dark:bg-gray-800')
        wrapper.unmount()
    })

    it('disables the zoom controls until the document has loaded', async () => {
        const wrapper = mountViewer()
        await flushPromises()

        for (const id of ['image-viewer-zoom-in', 'image-viewer-zoom-out', 'image-viewer-reset']) {
            expect(wrapper.get(`[data-test="${id}"]`).attributes('disabled')).toBeDefined()
        }

        await loadImage(wrapper, 800, 600)
        expect(wrapper.get('[data-test="image-viewer-zoom-in"]').attributes('disabled')).toBeUndefined()
        expect(wrapper.get('[data-test="image-viewer-reset"]').attributes('disabled')).toBeUndefined()
        wrapper.unmount()
    })

    it('keeps the toolbar compact on small screens', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 800, 600)

        // The Reset label and the header subtitle collapse on mobile.
        expect(wrapper.get('[data-test="image-viewer-reset"] span').classes()).toContain('hidden')
        expect(wrapper.get('header p').classes()).toContain('hidden')
        wrapper.unmount()
    })
})


describe('ImageViewer — error handling and accessibility', () => {
    it('shows a friendly error state instead of a broken image', async () => {
        const wrapper = mountViewer()
        fire(wrapper.get('[data-test="image-viewer-image"]').element, 'error')
        await flushPromises()

        expect(wrapper.find('[data-test="image-viewer-error"]').exists()).toBe(true)
        expect(wrapper.find('[data-test="image-viewer-image"]').exists()).toBe(false)
        expect(wrapper.text()).toContain('Image unavailable')
        wrapper.unmount()
    })

    it('shows the error state when no src is provided', async () => {
        const wrapper = mountViewer({ src: '' })
        expect(wrapper.find('[data-test="image-viewer-error"]').exists()).toBe(true)
        wrapper.unmount()
    })

    it('exposes aria-labels on every control', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)

        expect(wrapper.get('[data-test="image-viewer-close"]').attributes('aria-label')).toBe('Close image viewer')
        expect(wrapper.get('[data-test="image-viewer-zoom-in"]').attributes('aria-label')).toBe('Zoom in')
        expect(wrapper.get('[data-test="image-viewer-zoom-out"]').attributes('aria-label')).toBe('Zoom out')
        expect(wrapper.get('[data-test="image-viewer-reset"]').attributes('aria-label')).toBe('Reset zoom')
        wrapper.unmount()
    })

    it('gives every control a 44px minimum touch target', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 800, 600)

        for (const id of [
            'image-viewer-close',
            'image-viewer-zoom-in',
            'image-viewer-zoom-out',
            'image-viewer-reset',
        ]) {
            const classes = wrapper.get(`[data-test="${id}"]`).classes()
            expect(classes).toContain('min-h-[44px]')
            expect(classes).toContain('min-w-[44px]')
        }
        wrapper.unmount()
    })

    it('spaces the toolbar buttons at least 8px apart', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 800, 600)
        expect(wrapper.get('[data-test="image-viewer-toolbar"]').classes()).toContain('gap-2')
        wrapper.unmount()
    })

    it('moves focus into the dialog on open and traps Tab', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 800, 600)
        await flushPromises()

        const close = wrapper.get('[data-test="image-viewer-close"]').element
        expect(document.activeElement).toBe(close)

        // Tab from the last control wraps back to the first.
        const reset = wrapper.get('[data-test="image-viewer-reset"]').element as HTMLElement
        reset.focus()
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab' }))
        await flushPromises()
        expect(document.activeElement).toBe(close)
        wrapper.unmount()
    })

    it('announces zoom changes for screen readers', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 4000, 1000)
        await clickControl(wrapper, 'image-viewer-zoom-in')
        expect(wrapper.get('[data-test="image-viewer-status"]').text()).toContain('Zoom 125 percent')
        wrapper.unmount()
    })

    it('hides the keyboard hint on small screens so it never crowds the toolbar', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 400, 300)
        expect(wrapper.get('[data-test="image-viewer-toolbar"]').classes()).toContain('flex')
        expect(wrapper.get('[data-test="image-viewer-hint"]').classes()).toContain('hidden')
        wrapper.unmount()
    })

    it('releases the scroll lock and listeners when unmounted while open', async () => {
        const wrapper = mountViewer()
        await loadImage(wrapper, 800, 600)
        expect(document.body.style.overflow).toBe('hidden')

        wrapper.unmount()
        expect(document.body.style.overflow).not.toBe('hidden')
        // Keyboard handling must be detached too.
        expect(() => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))).not.toThrow()
    })
})

