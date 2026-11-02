{{--
    The event image's position on the website's card (EventResource, Image step).
    Staff drag the image inside a card-shaped frame and zoom it; the state is
    {x, y, zoom} (Event::normalizeImagePosition(), which also clamps it on save).
    x/y are object-position percentages and zoom scales around that same point,
    exactly as the website's card draws it (EventImage in EventsContent.js), so
    the frame here is what the card shows. Arrow keys move it, + and - zoom.
    Inline styles, like event-preview, so nothing depends on the theme build.
--}}
@php
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if ($imageUrl)
        <div
            wire:key="event-image-position-{{ md5($imageUrl) }}"
            x-data="{
                state: $wire.$entangle(@js($statePath)),
                x: 50,
                y: 50,
                zoom: 1,
                drag: null,
                init() {
                    this.load(this.state)
                    this.$watch('state', (state) => this.load(state))
                },
                load(state) {
                    this.x = Number(state?.x ?? 50)
                    this.y = Number(state?.y ?? 50)
                    this.zoom = Number(state?.zoom ?? 1)
                },
                save() {
                    this.state = { x: Math.round(this.x * 10) / 10, y: Math.round(this.y * 10) / 10, zoom: Math.round(this.zoom * 100) / 100 }
                },
                clamp: (value) => Math.min(100, Math.max(0, value)),
                {{-- How far the zoomed image overhangs the frame, in pixels: the distance a drag from 0% to 100% covers. --}}
                overhang() {
                    const img = this.$refs.img
                    const frame = this.$refs.frame.getBoundingClientRect()
                    if (! img.naturalWidth) return { x: 0, y: 0 }
                    const scale = Math.max(frame.width / img.naturalWidth, frame.height / img.naturalHeight) * this.zoom
                    return { x: img.naturalWidth * scale - frame.width, y: img.naturalHeight * scale - frame.height }
                },
                start(event) {
                    this.drag = { px: event.clientX, py: event.clientY, x: this.x, y: this.y, overhang: this.overhang() }
                    this.$refs.frame.setPointerCapture(event.pointerId)
                },
                move(event) {
                    if (! this.drag) return
                    const { overhang } = this.drag
                    if (overhang.x > 0) this.x = this.clamp(this.drag.x - (event.clientX - this.drag.px) / overhang.x * 100)
                    if (overhang.y > 0) this.y = this.clamp(this.drag.y - (event.clientY - this.drag.py) / overhang.y * 100)
                },
                end() {
                    if (! this.drag) return
                    this.drag = null
                    this.save()
                },
                nudge(dx, dy) {
                    this.x = this.clamp(this.x + dx)
                    this.y = this.clamp(this.y + dy)
                    this.save()
                },
                setZoom(value) {
                    this.zoom = Math.min({{ \App\Models\Event::MAX_IMAGE_ZOOM }}, Math.max(1, value))
                    this.save()
                },
            }"
            style="max-width: 560px;"
        >
            <div
                x-ref="frame"
                tabindex="0"
                role="img"
                aria-label="The image as the event card shows it. Drag, or use the arrow keys, to move it; plus and minus zoom."
                x-on:pointerdown.prevent="start($event)"
                x-on:pointermove="move($event)"
                x-on:pointerup="end()"
                x-on:pointercancel="end()"
                x-on:keydown.left.prevent="nudge(2, 0)"
                x-on:keydown.right.prevent="nudge(-2, 0)"
                x-on:keydown.up.prevent="nudge(0, 2)"
                x-on:keydown.down.prevent="nudge(0, -2)"
                x-on:keydown.equal.prevent="setZoom(zoom + 0.1)"
                x-on:keydown.minus.prevent="setZoom(zoom - 0.1)"
                x-bind:style="{ cursor: drag ? 'grabbing' : 'grab' }"
                style="position: relative; aspect-ratio: 16 / 9; overflow: hidden; border-radius: 12px; background: #ede3f5; touch-action: none; user-select: none; outline-offset: 2px;"
            >
                <img
                    x-ref="img"
                    src="{{ $imageUrl }}"
                    alt=""
                    draggable="false"
                    x-bind:style="{ objectPosition: `${x}% ${y}%`, transformOrigin: `${x}% ${y}%`, transform: `scale(${zoom})` }"
                    style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; pointer-events: none;"
                >
            </div>

            <div style="display: flex; align-items: center; gap: 12px; margin-top: 12px; font-size: 14px;">
                <label for="{{ $statePath }}-zoom" style="font-weight: 600;">Zoom</label>
                <input
                    id="{{ $statePath }}-zoom"
                    type="range"
                    min="1"
                    max="{{ \App\Models\Event::MAX_IMAGE_ZOOM }}"
                    step="0.05"
                    x-bind:value="zoom"
                    x-on:input="zoom = Number($event.target.value)"
                    x-on:change="save()"
                    style="flex: 1; accent-color: #5C0F8B;"
                >
                <span x-text="`${zoom.toFixed(1)}×`" style="width: 3em; text-align: right; color: #6b7280;"></span>
                <button
                    type="button"
                    x-on:click="x = 50; y = 50; zoom = 1; save()"
                    style="font-weight: 600; color: #5C0F8B;"
                >Reset</button>
            </div>
        </div>
    @else
        <p style="font-size: 14px; color: #6b7280;">The image can be positioned here once it has finished uploading.</p>
    @endif
</x-dynamic-component>
