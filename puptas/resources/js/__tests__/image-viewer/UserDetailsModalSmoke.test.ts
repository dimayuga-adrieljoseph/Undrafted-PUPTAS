import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import UserDetailsModal from '@/Pages/Applications/UserDetailsModal.vue'

describe('UserDetailsModal runtime', () => {
    it('emits open-image with the file and a readable label', async () => {
        const wrapper = mount(UserDetailsModal, {
            props: {
                selectedUser: { id: 1, firstname: 'Juan', lastname: 'Dela Cruz' },
                selectedUserFiles: {
                    report_card: { url: '/files/1/preview', isImage: true },
                },
            },
        })

        const thumb = wrapper.findAll('img').find((i) => i.attributes('src') === '/files/1/preview')
        expect(thumb).toBeTruthy()

        await thumb!.trigger('click')

        const events = wrapper.emitted('open-image')
        expect(events).toBeTruthy()
        expect(events![0][0]).toEqual({ url: '/files/1/preview', isImage: true })
        expect(typeof events![0][1]).toBe('string')
        expect(String(events![0][1]).length).toBeGreaterThan(0)
        wrapper.unmount()
    })
})
