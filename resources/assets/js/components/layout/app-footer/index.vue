<template>
  <footer
    ref="root"
    class="flex flex-col relative z-20 bg-k-bg-secondary h-k-footer-height"
    @mousemove="showControls"
    @contextmenu.prevent="requestContextMenu"
  >
    <AudioPlayer v-show="playable" />

    <div class="fullscreen-backdrop hidden" />

    <div class="wrapper relative flex flex-1">
      <SongInfo />
      <PlaybackControls />
      <ExtraControls />
    </div>

    <Transition>
      <UpNext v-show="showingUpNext" :playable="nextPlayable" class="up-next" />
    </Transition>
  </footer>
</template>

<script lang="ts" setup>
import { throttle } from 'lodash'
import { computed, nextTick, ref, watch } from 'vue'
import { useFullscreen } from '@vueuse/core'
import { eventBus } from '@/utils/eventBus'
import { isSong } from '@/utils/typeGuards'
import { isAudioContextSupported } from '@/utils/supports'
import { defineAsyncComponent, requireInjection } from '@/utils/helpers'
import { CurrentPlayableKey } from '@/symbols'
import { artistStore } from '@/stores/artistStore'
import { preferenceStore } from '@/stores/preferenceStore'
import { audioService } from '@/services/audioService'
import { playbackService } from '@/services/playbackService'

import AudioPlayer from '@/components/layout/app-footer/AudioPlayer.vue'
import SongInfo from '@/components/layout/app-footer/FooterSongInfo.vue'
import ExtraControls from '@/components/layout/app-footer/FooterExtraControls.vue'
import PlaybackControls from '@/components/layout/app-footer/FooterPlaybackControls.vue'

const UpNext = defineAsyncComponent(() => import('@/components/layout/app-footer/UpNext.vue'))

const playable = requireInjection(CurrentPlayableKey, ref())
let hideControlsTimeout: number

const root = ref<HTMLElement>()
const artist = ref<Artist>()
const nextPlayable = ref<Playable | null>(null)

const { isFullscreen, toggle: toggleFullscreen } = useFullscreen(root)

const showingUpNext = computed(() => nextPlayable.value && isFullscreen.value)

const requestContextMenu = (event: MouseEvent) => {
  if (document.fullscreenElement || !playable.value) {
    return
  }

  eventBus.emit('PLAYABLE_CONTEXT_MENU_REQUESTED', event, playable.value)
}

watch(playable, async () => {
  if (!playable.value) {
    return
  }

  if (isSong(playable.value)) {
    artist.value = await artistStore.resolve(playable.value.artist_id)
  }
})

const appBackgroundImage = computed(() => {
  if (!playable.value || !isSong(playable.value)) {
    return 'none'
  }

  const src = artist.value?.image ?? playable.value.album_cover
  return src ? `url(${src})` : 'none'
})

const initPlaybackRelatedServices = async () => {
  const plyrWrapper = document.querySelector<HTMLElement>('.plyr')

  if (!plyrWrapper) {
    await nextTick()
    await initPlaybackRelatedServices()
    return
  }

  playbackService.init(plyrWrapper)
  isAudioContextSupported && audioService.init(playbackService.player.media)
}

watch(preferenceStore.initialized, async initialized => {
  if (!initialized) {
    return
  }
  await initPlaybackRelatedServices()
}, { immediate: true })

const setupControlHidingTimer = () => {
  hideControlsTimeout = window.setTimeout(() => root.value?.classList.add('hide-controls'), 5000)
}

const showControls = throttle(() => {
  if (!document.fullscreenElement) {
    return
  }

  root.value?.classList.remove('hide-controls')
  window.clearTimeout(hideControlsTimeout)
  setupControlHidingTimer()
}, 100)

watch(isFullscreen, fullscreen => {
  if (fullscreen) {
    setupControlHidingTimer()
    root.value?.classList.remove('hide-controls')
  } else {
    window.clearTimeout(hideControlsTimeout)
  }
})

eventBus.on('FULLSCREEN_TOGGLE', () => toggleFullscreen())
  .on('UP_NEXT', next => (nextPlayable.value = next))
</script>

<style lang="postcss" scoped>
.v-enter-active,
.v-leave-active {
  transition: opacity 2s ease;
}

.v-enter-from,
.v-leave-to {
  opacity: 0;
}

footer {
  box-shadow: 0 0 30px 20px rgba(0, 0, 0, 0.2);

  .fullscreen-backdrop {
    background-image: v-bind(appBackgroundImage);
  }

  &:fullscreen {
    padding: calc(100vh - 9rem) 5vw 0;
    @apply bg-none;

    &.hide-controls :not(.fullscreen-backdrop, .up-next, .up-next *) {
      transition: opacity 2s ease-in-out !important; /* overriding all children's custom transition, if any */
      @apply opacity-0;
    }

    .wrapper {
      @apply z-[3];
    }

    &::before {
      @apply bg-black bg-repeat absolute top-0 left-0 opacity-50 z-[1] pointer-events-none -m-[20rem];
      content: '';
      background-image:
        linear-gradient(135deg, #111 25%, transparent 25%), linear-gradient(225deg, #111 25%, transparent 25%),
        linear-gradient(45deg, #111 25%, transparent 25%), linear-gradient(315deg, #111 25%, rgba(255, 255, 255, 0) 25%);
      background-position:
        6px 0,
        6px 0,
        0 0,
        0 0;
      background-size: 6px 6px;
      width: calc(100% + 40rem);
      height: calc(100% + 40rem);
      transform: rotate(10deg);
    }

    &::after {
      background-image: linear-gradient(0deg, rgba(0, 0, 0, 1) 0%, rgba(255, 255, 255, 0) 30vh);
      content: '';
      @apply absolute w-full h-full top-0 left-0 z-[1] pointer-events-none;
    }

    .fullscreen-backdrop {
      @apply saturate-[0.2] block absolute top-0 left-0 w-full h-full z-0 bg-cover bg-no-repeat bg-top;
    }
  }
}
</style>
