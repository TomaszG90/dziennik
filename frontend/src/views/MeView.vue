<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiClient, ApiError, updateMyProfile } from '@/api'
import { getTravelStats, type TravelStats } from '@/api/stats'
import { getPublicAvatarUrl, getPublicProfiles, type PublicProfile } from '@/api/publicProfiles'
import type { UpdateProfilePayload } from '@/api/auth'
import type { ProfilePrivacySettings } from '@/api/types'
import { getTrips } from '@/api/trips'
import {
  acceptFriendRequest,
  acceptTripInvitation,
  cancelFriendRequest,
  declineFriendRequest,
  declineTripInvitation,
  getFriends,
  getFriendRequests,
  getMyTripInvitations,
  removeFriend,
  sendFriendRequest,
  sendInviteEmail,
  type Friend,
  type FriendRequest,
  type TripInvitation,
} from '@/api/friends'
import { useAuthStore } from '@/stores/auth'
import UserTripsList from '@/components/trips/UserTripsList.vue'

type ProfileTab = 'trips' | 'friends' | 'stats' | 'badges'
type StatsLoadState = 'idle' | 'loading' | 'loaded' | 'error'
type ProfileFieldKey = 'name' | 'email' | 'first_name' | 'last_name' | 'city' | 'birth_date'

interface BadgeProgress {
  id: string
  icon: string
  name: string
  description: string
  current: number
  target: number
  progress: string
}

interface ProfileForm {
  name: string
  email: string
  first_name: string
  last_name: string
  city: string
  birth_date: string
  avatar_path: string
}

interface ProfileField {
  key: ProfileFieldKey
  privacyKey: keyof ProfilePrivacySettings
  label: string
  inputType: 'text' | 'email' | 'date'
  autocomplete: string
}

const auth = useAuthStore()
const activeTab = ref<ProfileTab>('trips')
const publicProfiles = ref<PublicProfile[]>([])
const friendSearch = ref('')
const friendsCurrentPage = ref(1)
const friendsLastPage = ref(1)
const friendsTotal = ref(0)
const friendsLoading = ref(false)
const friendsError = ref('')
const friendSearchSubmitted = ref(false)
const friendSearchQuery = ref('')
let friendsRequestId = 0

// Accepted friends
const friends = ref<Friend[]>([])
const friendsListLoading = ref(false)
const friendsListError = ref('')

// Friend requests
const incomingRequests = ref<FriendRequest[]>([])
const outgoingRequests = ref<FriendRequest[]>([])
const requestsLoading = ref(false)
const requestsError = ref('')
const requestsTab = ref<'incoming' | 'outgoing'>('incoming')

// Friend state map: tracks whether a profile is already a friend or has a pending request
const friendStateMap = ref<Record<number, 'friend' | 'pending_sent' | 'pending_received' | null>>({})

// Email invite
const inviteEmail = ref('')
const inviteLoading = ref(false)
const inviteSuccess = ref('')
const inviteError = ref('')
const showInvitePreview = ref(false)
const invitePreviewTo = ref('')

// Trip invitations
const tripInvitations = ref<TripInvitation[]>([])
const tripInvitationsLoading = ref(false)
const tripInvitationsError = ref('')

const inviteLink = computed(() => {
  const origin = window.location.origin
  return `${origin}/users/${auth.user?.id}`
})

const travelStats = ref<TravelStats | null>(null)
const statsLoadState = ref<StatsLoadState>('idle')
const statsError = ref('')
const selectedYear = ref<number | null>(null)
const availableYears = ref<number[]>([])
const yearsLoading = ref(false)
const yearsLoaded = ref(false)
const yearsError = ref(false)
let statsRequestId = 0
const statsCache = new Map<number | null, TravelStats>()
const profileVisibility = ref<'private' | 'public'>(auth.user?.profile_visibility ?? 'private')
const defaultPrivacySettings: ProfilePrivacySettings = {
  name: false,
  email: false,
  firstName: false,
  lastName: false,
  city: false,
  birthDate: false,
  avatar: false,
}
const fieldVisibility = reactive<ProfilePrivacySettings>({
  ...defaultPrivacySettings,
  ...auth.user?.privacy_settings,
})
const form = reactive<ProfileForm>({
  name: auth.user?.name ?? '',
  email: auth.user?.email ?? '',
  first_name: auth.user?.first_name ?? '',
  last_name: auth.user?.last_name ?? '',
  city: auth.user?.city ?? '',
  birth_date: auth.user?.birth_date ?? '',
  avatar_path: auth.user?.avatar_path ?? '1.png',
})
const profileFields: ProfileField[] = [
  {
    key: 'name',
    privacyKey: 'name',
    label: 'Nazwa profilu',
    inputType: 'text',
    autocomplete: 'nickname',
  },
  {
    key: 'email',
    privacyKey: 'email',
    label: 'E-mail',
    inputType: 'email',
    autocomplete: 'email',
  },
  {
    key: 'first_name',
    privacyKey: 'firstName',
    label: 'Imię',
    inputType: 'text',
    autocomplete: 'given-name',
  },
  {
    key: 'last_name',
    privacyKey: 'lastName',
    label: 'Nazwisko',
    inputType: 'text',
    autocomplete: 'family-name',
  },
  {
    key: 'city',
    privacyKey: 'city',
    label: 'Miejscowość',
    inputType: 'text',
    autocomplete: 'address-level2',
  },
  {
    key: 'birth_date',
    privacyKey: 'birthDate',
    label: 'Data urodzenia',
    inputType: 'date',
    autocomplete: 'bday',
  },
]
const avatarDialog = ref<HTMLDialogElement | null>(null)
const showVisibilityInfo = ref(false)
const avatarFiles = Array.from({ length: 10 }, (_, index) => `${index + 1}.png`)
const isSaving = ref(false)
const saveError = ref('')
const statusMessage = ref('')
const friendsCount: number = 0
const friendsCountLabel = computed(() => {
  if (friendsCount === 1) return '1 znajomy'
  return `${friendsCount} znajomych`
})
const transportLabels: Record<string, string> = {
  walking: 'Pieszo',
  car: 'Samochodem',
  plane: 'Samolotem',
  train: 'Pociągiem',
  other: 'Innym środkiem',
}
const transportColors: Record<string, string> = {
  walking: '#2f855a',
  car: '#d97706',
  plane: '#2563eb',
  train: '#7c3aed',
  other: '#64748b',
}
const transportDistances = computed(() =>
  Object.entries(travelStats.value?.distance_by_transport ?? {})
    .filter(([, distance]) => distance > 0)
    .sort((first, second) => second[1] - first[1]),
)
const totalTransportDistance = computed(() =>
  transportDistances.value.reduce((total, [, distance]) => total + distance, 0),
)
const maxTransportDistance = computed(() =>
  Math.max(0, ...transportDistances.value.map(([, distance]) => distance)),
)
const transportChartSegments = computed(() => {
  const circumference = 2 * Math.PI * 44
  let offset = 0

  return transportDistances.value.map(([transport, distance]) => {
    const length = (distance / totalTransportDistance.value) * circumference
    const segment = {
      transport,
      color: transportColors[transport] ?? '#386a54',
      percentage: (distance / totalTransportDistance.value) * 100,
      dashArray: `${length} ${circumference - length}`,
      dashOffset: `${-offset}`,
    }
    offset += length
    return segment
  })
})
const badgeProgress = computed<BadgeProgress[]>(() => {
  const stats = travelStats.value
  if (!stats) return []

  const transportCount = transportDistances.value.length
  const countriesCount = stats.countries.length

  return [
    {
      id: 'first-trip',
      icon: '🧭',
      name: 'Pierwsza podróż',
      description: 'Zapisz swoją pierwszą podróż.',
      current: stats.trips_count,
      target: 1,
      progress: `${stats.trips_count} / 1 podróż`,
    },
    {
      id: 'five-trips',
      icon: '🎒',
      name: 'W drodze',
      description: 'Zapisz pięć podróży.',
      current: stats.trips_count,
      target: 5,
      progress: `${stats.trips_count} / 5 podróży`,
    },
    {
      id: 'first-country',
      icon: '🌍',
      name: 'Nowy kraj',
      description: 'Odwiedź swoje pierwsze państwo.',
      current: countriesCount,
      target: 1,
      progress: `${countriesCount} / 1 kraj`,
    },
    {
      id: 'five-countries',
      icon: '🗺️',
      name: 'Odkrywca',
      description: 'Odwiedź pięć różnych państw.',
      current: countriesCount,
      target: 5,
      progress: `${countriesCount} / 5 krajów`,
    },
    {
      id: 'thousand-km',
      icon: '✈️',
      name: 'Tysiąc kilometrów',
      description: 'Przebyj łącznie 1000 km.',
      current: stats.total_distance,
      target: 1000,
      progress: `${formatDistance(stats.total_distance)} / 1 000 km`,
    },
    {
      id: 'twenty-five-places',
      icon: '📍',
      name: 'Ciekawość świata',
      description: 'Odwiedź 25 miejsc.',
      current: stats.places_count,
      target: 25,
      progress: `${stats.places_count} / 25 miejsc`,
    },
    {
      id: 'transport-mix',
      icon: '🚆',
      name: 'Różne drogi',
      description: 'Podróżuj trzema różnymi środkami transportu.',
      current: transportCount,
      target: 3,
      progress: `${transportCount} / 3 środki transportu`,
    },
  ]
})
const unlockedBadgesCount = computed(
  () => badgeProgress.value.filter((badge) => badge.current >= badge.target).length,
)

const avatarUrl = (filename: string) =>
  new URL(`/avatars/${filename}`, apiClient.defaults.baseURL).toString()

function formatDistance(distance: number): string {
  return new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 1 }).format(distance)
}

function countryName(countryCode: string): string {
  try {
    return new Intl.DisplayNames(['pl'], { type: 'region' }).of(countryCode) ?? countryCode
  } catch {
    return countryCode
  }
}

async function loadStats() {
  const year = selectedYear.value
  const requestId = ++statsRequestId
  const cachedStats = statsCache.get(year)
  if (cachedStats) {
    travelStats.value = cachedStats
    statsLoadState.value = 'loaded'
    statsError.value = ''
    return
  }

  statsLoadState.value = 'loading'
  statsError.value = ''

  try {
    const stats = await getTravelStats(year ?? undefined)
    statsCache.set(year, stats)
    if (requestId !== statsRequestId) return
    travelStats.value = stats
    statsLoadState.value = 'loaded'
  } catch (error) {
    if (requestId !== statsRequestId) return
    statsError.value = error instanceof ApiError ? error.message : 'Nie udało się pobrać statystyk.'
    statsLoadState.value = 'error'
  }
}

function yearFromDate(date: string | null): number | null {
  const match = date?.match(/^(\d{4})-\d{2}-\d{2}/)
  return match ? Number(match[1]) : null
}

async function loadAvailableYears() {
  if (yearsLoading.value || yearsLoaded.value) return

  yearsLoading.value = true
  yearsError.value = false
  try {
    const trips = await getTrips()
    const years = new Set<number>()
    for (const trip of trips) {
      const startYear = yearFromDate(trip.start_date)
      const endYear = yearFromDate(trip.end_date)
      if (startYear !== null) years.add(startYear)
      if (endYear !== null) years.add(endYear)
    }
    availableYears.value = [...years].sort((first, second) => second - first)
    yearsLoaded.value = true
  } catch {
    yearsError.value = true
  } finally {
    yearsLoading.value = false
  }
}

function selectTab(tab: ProfileTab) {
  activeTab.value = tab
  if (tab === 'stats' || tab === 'badges') {
    void loadStats()
  }
  if (tab === 'stats') void loadAvailableYears()
  if (tab === 'friends') {
    void loadFriendsAndRequests()
  }
}

async function loadFriendsAndRequests() {
  friendsListLoading.value = true
  friendsListError.value = ''
  requestsLoading.value = true
  requestsError.value = ''
  tripInvitationsLoading.value = true
  tripInvitationsError.value = ''

  try {
    const [friendsData, incomingData, outgoingData, tripInvData] = await Promise.all([
      getFriends(),
      getFriendRequests('incoming'),
      getFriendRequests('outgoing'),
      getMyTripInvitations(),
    ])
    friends.value = friendsData
    incomingRequests.value = incomingData
    outgoingRequests.value = outgoingData
    tripInvitations.value = tripInvData

    // Build state map for search results
    const map: Record<number, 'friend' | 'pending_sent' | 'pending_received' | null> = {}
    for (const f of friendsData) {
      map[f.id] = 'friend'
    }
    for (const req of incomingData) {
      const otherId = req.sender_id
      if (!map[otherId]) map[otherId] = 'pending_received'
    }
    for (const req of outgoingData) {
      const otherId = req.recipient_id
      if (!map[otherId]) map[otherId] = 'pending_sent'
    }
    friendStateMap.value = map
  } catch (cause: unknown) {
    friendsListError.value =
      cause instanceof Error ? cause.message : 'Nie udało się pobrać listy znajomych.'
    requestsError.value = friendsListError.value
  } finally {
    friendsListLoading.value = false
    requestsLoading.value = false
    tripInvitationsLoading.value = false
  }
}

async function sendAddFriendRequest(profileId: number) {
  try {
    await sendFriendRequest(profileId)
    friendStateMap.value[profileId] = 'pending_sent'
    await loadFriendsAndRequests()
  } catch (cause: unknown) {
    if (cause instanceof ApiError) {
      friendsError.value = cause.message || 'Nie udało się wysłać zaproszenia.'
    } else {
      friendsError.value = 'Nie udało się wysłać zaproszenia.'
    }
  }
}

async function acceptRequest(requestId: number) {
  try {
    await acceptFriendRequest(requestId)
    await loadFriendsAndRequests()
  } catch {
    requestsError.value = 'Nie udało się zaakceptować zaproszenia.'
  }
}

async function declineRequest(requestId: number) {
  try {
    await declineFriendRequest(requestId)
    await loadFriendsAndRequests()
  } catch {
    requestsError.value = 'Nie udało się odrzucić zaproszenia.'
  }
}

async function cancelRequest(requestId: number) {
  try {
    await cancelFriendRequest(requestId)
    await loadFriendsAndRequests()
  } catch {
    requestsError.value = 'Nie udało się wycofać zaproszenia.'
  }
}

async function removeFriendRelationship(friendId: number) {
  try {
    await removeFriend(friendId)
    delete friendStateMap.value[friendId]
    await loadFriendsAndRequests()
  } catch {
    friendsListError.value = 'Nie udało się usunąć znajomego.'
  }
}

async function acceptTripInv(tripId: number, invitationId: number) {
  try {
    await acceptTripInvitation(tripId, invitationId)
    await loadFriendsAndRequests()
  } catch {
    tripInvitationsError.value = 'Nie udało się zaakceptować zaproszenia.'
  }
}

async function declineTripInv(tripId: number, invitationId: number) {
  try {
    await declineTripInvitation(tripId, invitationId)
    await loadFriendsAndRequests()
  } catch {
    tripInvitationsError.value = 'Nie udało się odrzucić zaproszenia.'
  }
}

function getFriendState(profileId: number): 'friend' | 'pending_sent' | 'pending_received' | null {
  return friendStateMap.value[profileId] ?? null
}

function friendName(friend: Friend): string {
  return friend.name || 'Użytkownik'
}

function requestUserName(request: FriendRequest): string {
  const profile = request.user
  const fullName = [profile.first_name, profile.last_name].filter(Boolean).join(' ')
  return profile.name || fullName || 'Użytkownik'
}

onMounted(() => {
  // Preload friends if already on the friends tab
  if (activeTab.value === 'friends') {
    void loadFriendsAndRequests()
  }
})

function showInvitePreviewDialog() {
  const email = inviteEmail.value.trim()
  if (!email) return
  invitePreviewTo.value = email
  showInvitePreview.value = true
}

async function confirmAndSendInvite() {
  showInvitePreview.value = false
  inviteLoading.value = true
  inviteSuccess.value = ''
  inviteError.value = ''

  try {
    await sendInviteEmail(invitePreviewTo.value.trim())
    inviteSuccess.value = 'Zaproszenie zostało wysłane!'
    inviteEmail.value = ''
  } catch (cause: unknown) {
    if (cause instanceof ApiError) {
      inviteError.value = cause.message || 'Nie udało się wysłać zaproszenia.'
    } else {
      inviteError.value = 'Nie udało się wysłać zaproszenia.'
    }
  } finally {
    inviteLoading.value = false
  }
}

function closeInvitePreview() {
  showInvitePreview.value = false
}

function publicProfileName(profile: PublicProfile): string {
  const fullName = [profile.first_name, profile.last_name].filter(Boolean).join(' ')
  return profile.name || fullName || 'Użytkownik'
}

async function loadPublicProfiles(page: number) {
  if (!friendSearchQuery.value) return

  const requestId = ++friendsRequestId
  friendsLoading.value = true
  friendsError.value = ''

  try {
    const result = await getPublicProfiles(page, friendSearchQuery.value)
    if (requestId !== friendsRequestId) return
    publicProfiles.value = result.data
    friendsCurrentPage.value = result.meta.current_page
    friendsLastPage.value = result.meta.last_page
    friendsTotal.value = result.meta.total
  } catch (cause: unknown) {
    if (requestId !== friendsRequestId) return
    friendsError.value =
      cause instanceof Error ? cause.message : 'Nie udało się wyszukać publicznych profili.'
  } finally {
    if (requestId === friendsRequestId) friendsLoading.value = false
  }
}

function searchPublicProfiles() {
  friendSearchQuery.value = friendSearch.value.trim()
  friendSearchSubmitted.value = friendSearchQuery.value.length > 0
  friendsError.value = ''

  if (!friendSearchSubmitted.value) {
    friendsRequestId += 1
    friendsLoading.value = false
    publicProfiles.value = []
    friendsTotal.value = 0
    friendsCurrentPage.value = 1
    friendsLastPage.value = 1
    return
  }

  void loadPublicProfiles(1)
}

function selectStatsYear(event: Event) {
  const value = (event.target as HTMLSelectElement).value
  selectedYear.value = value === '' ? null : Number(value)
  void loadStats()
}

function retryAvailableYears() {
  yearsError.value = false
  void loadAvailableYears()
}

function transportBarWidth(distance: number): string {
  if (maxTransportDistance.value === 0) return '0%'
  return `${Math.max(2, (distance / maxTransportDistance.value) * 100)}%`
}

function badgeIsUnlocked(badge: BadgeProgress): boolean {
  return badge.current >= badge.target
}

function badgeProgressPercent(badge: BadgeProgress): number {
  return Math.min(100, Math.round((badge.current / badge.target) * 100))
}

function toggleProfileVisibility() {
  profileVisibility.value = profileVisibility.value === 'public' ? 'private' : 'public'
}

function toggleFieldVisibility(field: keyof ProfilePrivacySettings) {
  if (profileVisibility.value === 'private') return
  fieldVisibility[field] = !fieldVisibility[field]
}

function openAvatarDialog() {
  avatarDialog.value?.showModal()
}

function closeAvatarDialog() {
  avatarDialog.value?.close()
}

function selectAvatar(filename: string) {
  form.avatar_path = filename
  closeAvatarDialog()
}

async function saveProfile() {
  isSaving.value = true
  saveError.value = ''
  statusMessage.value = ''

  const payload: UpdateProfilePayload = {
    name: form.name,
    first_name: form.first_name,
    last_name: form.last_name,
    city: form.city,
    birth_date: form.birth_date || null,
    avatar_path: form.avatar_path,
    profile_visibility: profileVisibility.value,
    privacy_settings: { ...fieldVisibility },
  }

  try {
    const updatedProfile = await updateMyProfile(payload)
    auth.user = updatedProfile
    statusMessage.value = 'Zmiany profilu zostały zapisane.'
  } catch (error) {
    saveError.value =
      error instanceof ApiError
        ? (Object.values(error.validationErrors ?? {})[0]?.[0] ?? error.message)
        : 'Nie udało się zapisać profilu. Spróbuj ponownie.'
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <main class="profile-page">
    <div class="profile-layout">
      <section class="profile-card profile-editor-card" aria-labelledby="profile-title">
        <header class="profile-header">
          <h1 id="profile-title">Edytuj profil</h1>

          <div class="visibility-control">
            <button
              class="visibility-button master-visibility"
              type="button"
              :aria-pressed="profileVisibility === 'public'"
              @click="toggleProfileVisibility"
            >
              <svg
                :class="{ 'eye-closed': profileVisibility === 'private' }"
                aria-hidden="true"
                viewBox="0 0 24 24"
              >
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                <circle cx="12" cy="12" r="3" />
                <path v-if="profileVisibility === 'private'" d="M3 3l18 18" />
              </svg>
              {{ profileVisibility === 'public' ? 'Profil publiczny' : 'Profil prywatny' }}
            </button>
            <button
              class="visibility-info-button"
              type="button"
              aria-label="Informacje o widoczności profilu"
              aria-controls="visibility-info"
              :aria-expanded="showVisibilityInfo"
              @click="showVisibilityInfo = !showVisibilityInfo"
            >
              <svg aria-hidden="true" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 11v5m0-8h.01" />
              </svg>
            </button>
            <p
              v-if="showVisibilityInfo"
              id="visibility-info"
              class="visibility-info"
              role="tooltip"
            >
              Profil prywatny jest ukryty dla innych. Profil publiczny udostępnia tylko pola
              oznaczone jako publiczne. Ustawienia poszczególnych pól pozostają zapisane po
              przełączeniu profilu na prywatny.
            </p>
          </div>
        </header>

        <div class="avatar-row">
          <button
            class="avatar-preview"
            type="button"
            aria-label="Zmień awatar"
            @click="openAvatarDialog"
          >
            <img :src="avatarUrl(form.avatar_path)" alt="" />
          </button>
          <div class="avatar-details">
            <strong>{{ friendsCountLabel }}</strong>
          </div>
          <button
            class="visibility-button avatar-visibility"
            type="button"
            :disabled="profileVisibility === 'private'"
            :aria-pressed="fieldVisibility.avatar"
            :aria-label="
              fieldVisibility.avatar
                ? 'Ukryj zdjęcie profilowe i liczbę znajomych'
                : 'Pokaż zdjęcie profilowe i liczbę znajomych'
            "
            @click="toggleFieldVisibility('avatar')"
          >
            <svg aria-hidden="true" viewBox="0 0 24 24">
              <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
              <circle cx="12" cy="12" r="3" />
              <path v-if="!fieldVisibility.avatar" d="M3 3l18 18" />
            </svg>
            <span>{{ fieldVisibility.avatar ? 'Publiczne' : 'Prywatne' }}</span>
          </button>
        </div>

        <form class="profile-form" @submit.prevent="saveProfile">
          <div v-for="field in profileFields" :key="field.key" class="info-row">
            <div class="field">
              <label :for="field.key">{{ field.label }}</label>
              <div v-if="field.key === 'email'" class="static-field-value">
                <strong>{{ form.email }}</strong>
              </div>
              <input
                v-else
                :id="field.key"
                v-model="form[field.key]"
                :type="field.inputType"
                :autocomplete="field.autocomplete"
                :required="field.key === 'name'"
                maxlength="255"
              />
            </div>

            <button
              class="visibility-button field-visibility"
              type="button"
              :disabled="profileVisibility === 'private'"
              :aria-pressed="fieldVisibility[field.privacyKey]"
              :aria-label="
                fieldVisibility[field.privacyKey]
                  ? `Ukryj: ${field.label}`
                  : `Pokaż: ${field.label}`
              "
              @click="toggleFieldVisibility(field.privacyKey)"
            >
              <svg aria-hidden="true" viewBox="0 0 24 24">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                <circle cx="12" cy="12" r="3" />
                <path v-if="!fieldVisibility[field.privacyKey]" d="M3 3l18 18" />
              </svg>
              <span>{{ fieldVisibility[field.privacyKey] ? 'Publiczne' : 'Prywatne' }}</span>
            </button>
          </div>

          <p v-if="saveError" class="form-message form-error" role="alert">{{ saveError }}</p>
          <p v-if="statusMessage" class="form-message form-success" role="status">
            {{ statusMessage }}
          </p>
          <button class="save-button" type="submit" :disabled="isSaving">
            {{ isSaving ? 'Zapisywanie...' : 'Zapisz zmiany' }}
          </button>
        </form>
      </section>

      <section class="profile-card activity-card" aria-labelledby="activity-title">
        <h2 id="activity-title">Aktywność</h2>

        <div class="tabs" role="tablist" aria-label="Sekcje profilu">
          <button
            id="tab-trips"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'trips'"
            @click="selectTab('trips')"
          >
            Wycieczki
          </button>
          <button
            id="tab-friends"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'friends'"
            @click="selectTab('friends')"
          >
            Znajomi
          </button>
          <button
            id="tab-stats"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'stats'"
            @click="selectTab('stats')"
          >
            Statystyki
          </button>
          <button
            id="tab-badges"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'badges'"
            @click="selectTab('badges')"
          >
            Odznaki
          </button>
        </div>

        <div class="tab-panel" role="tabpanel" :aria-labelledby="`tab-${activeTab}`">
          <UserTripsList v-if="activeTab === 'trips'" />
          <section v-else-if="activeTab === 'friends'" class="friends-panel">
            <!-- Friend requests section -->
            <div class="requests-section">
              <h3>Zaproszenia</h3>
              <div class="requests-tabs" role="tablist" aria-label="Rodzaj zaproszeń">
                <button
                  type="button"
                  role="tab"
                  :aria-selected="requestsTab === 'incoming'"
                  @click="requestsTab = 'incoming'"
                >
                  Przychodzące ({{ incomingRequests.length }})
                </button>
                <button
                  type="button"
                  role="tab"
                  :aria-selected="requestsTab === 'outgoing'"
                  @click="requestsTab = 'outgoing'"
                >
                  Wysłane ({{ outgoingRequests.length }})
                </button>
              </div>

              <p v-if="requestsLoading" class="friends-status" role="status">Ładowanie zaproszeń...</p>
              <p v-else-if="requestsError" class="friends-status friends-error" role="alert">{{ requestsError }}</p>

              <template v-else>
                <div v-if="requestsTab === 'incoming'" class="requests-list">
                  <p v-if="incomingRequests.length === 0" class="empty-state">
                    Brak przychodzących zaproszeń.
                  </p>
                  <ul v-else class="friends-list">
                    <li v-for="req in incomingRequests" :key="req.id">
                      <img
                        v-if="req.user.avatar_path"
                        class="friend-avatar"
                        :src="getPublicAvatarUrl(req.user.avatar_path)"
                        alt=""
                      />
                      <span v-else class="friend-avatar friend-avatar-placeholder" aria-hidden="true">
                        {{ requestUserName(req).slice(0, 1).toLocaleUpperCase() }}
                      </span>
                      <div>
                        <strong>{{ requestUserName(req) }}</strong>
                        <span v-if="req.user.city">{{ req.user.city }}</span>
                      </div>
                      <button
                        class="accept-request-button"
                        type="button"
                        @click="acceptRequest(req.id)"
                      >
                        Akceptuj
                      </button>
                      <button
                        class="decline-request-button"
                        type="button"
                        @click="declineRequest(req.id)"
                      >
                        Odrzuć
                      </button>
                    </li>
                  </ul>
                </div>
                <div v-else class="requests-list">
                  <p v-if="outgoingRequests.length === 0" class="empty-state">
                    Brak wysłanych zaproszeń.
                  </p>
                  <ul v-else class="friends-list">
                    <li v-for="req in outgoingRequests" :key="req.id">
                      <img
                        v-if="req.user.avatar_path"
                        class="friend-avatar"
                        :src="getPublicAvatarUrl(req.user.avatar_path)"
                        alt=""
                      />
                      <span v-else class="friend-avatar friend-avatar-placeholder" aria-hidden="true">
                        {{ requestUserName(req).slice(0, 1).toLocaleUpperCase() }}
                      </span>
                      <div>
                        <strong>{{ requestUserName(req) }}</strong>
                        <span v-if="req.user.city">{{ req.user.city }}</span>
                      </div>
                      <span class="pending-badge">Oczekujące</span>
                      <button
                        class="cancel-request-button"
                        type="button"
                        @click="cancelRequest(req.id)"
                      >
                        Wycofaj
                      </button>
                    </li>
                  </ul>
                </div>
              </template>
            </div>

            <!-- Accepted friends list -->
            <div class="accepted-friends-section">
              <h3>Znajomi ({{ friends.length }})</h3>
              <p v-if="friendsListLoading" class="friends-status" role="status">Ładowanie znajomych...</p>
              <p v-else-if="friendsListError" class="friends-status friends-error" role="alert">{{ friendsListError }}</p>
              <p v-else-if="friends.length === 0" class="empty-state">
                Nie masz jeszcze znajomych. Wyszukaj profil poniżej i wyślij zaproszenie.
              </p>
              <ul v-else class="friends-list">
                <li v-for="friend in friends" :key="friend.id">
                  <img
                    v-if="friend.avatar_path"
                    class="friend-avatar"
                    :src="getPublicAvatarUrl(friend.avatar_path)"
                    alt=""
                  />
                  <span v-else class="friend-avatar friend-avatar-placeholder" aria-hidden="true">
                    {{ friendName(friend).slice(0, 1).toLocaleUpperCase() }}
                  </span>
                  <div>
                    <strong>{{ friendName(friend) }}</strong>
                  </div>
                  <RouterLink :to="{ name: 'public-profile', params: { id: friend.id } }">
                    Zobacz profil
                  </RouterLink>
                  <button
                    class="remove-friend-button"
                    type="button"
                    @click="removeFriendRelationship(friend.id)"
                  >
                    Usuń
                  </button>
                </li>
              </ul>
            </div>

            <!-- Trip invitations -->
            <div class="trip-invitations-section">
              <h3>Zaproszenia do wycieczek ({{ tripInvitations.length }})</h3>
              <p v-if="tripInvitationsLoading" class="friends-status" role="status">Ładowanie zaproszeń...</p>
              <p v-else-if="tripInvitationsError" class="friends-status friends-error" role="alert">{{ tripInvitationsError }}</p>
              <p v-else-if="tripInvitations.length === 0" class="empty-state">
                Nie masz żadnych zaproszeń do wycieczek.
              </p>
              <ul v-else class="friends-list">
                <li v-for="inv in tripInvitations" :key="inv.id">
                  <div class="trip-inv-info">
                    <span class="trip-inv-icon">🗺️</span>
                    <div>
                      <strong>{{ inv.inviter?.name || 'Użytkownik' }}</strong>
                      <span class="trip-inv-trip">zaprasza Cię do wycieczki</span>
                    </div>
                  </div>
                  <button
                    class="accept-request-button"
                    type="button"
                    @click="acceptTripInv(inv.trip_id, inv.id)"
                  >
                    Dołącz
                  </button>
                  <button
                    class="decline-request-button"
                    type="button"
                    @click="declineTripInv(inv.trip_id, inv.id)"
                  >
                    Odrzuć
                  </button>
                </li>
              </ul>
            </div>

            <!-- Invite by email -->
            <div class="invite-email-section">
              <h3>Zaprosz przez e-mail</h3>
              <p class="invite-description">
                Wyślij zaproszenie z linkiem do swojego profilu — odbiorca będzie mógł Cię łatwo znaleźć w aplikacji.
              </p>
              <form class="invite-email-form" @submit.prevent="showInvitePreviewDialog">
                <label for="invite-email">Adres e-mail</label>
                <div class="invite-email-controls">
                  <input
                    id="invite-email"
                    v-model="inviteEmail"
                    type="email"
                    placeholder="przyklad@email.com"
                    autocomplete="email"
                    required
                  />
                  <button type="submit" :disabled="inviteLoading">Wyślij zaproszenie</button>
                </div>
              </form>
              <p v-if="inviteSuccess" class="friends-status friends-success" role="status">{{ inviteSuccess }}</p>
              <p v-if="inviteError" class="friends-status friends-error" role="alert">{{ inviteError }}</p>
            </div>

            <!-- Search for new friends -->
            <div class="search-friends-section">
              <h3>Wyszukaj nowych znajomych</h3>
              <form class="friends-search-form" @submit.prevent="searchPublicProfiles">
                <label for="friend-search">Szukaj profilu</label>
                <div class="friends-search-controls">
                  <input
                    id="friend-search"
                    v-model="friendSearch"
                    type="search"
                    maxlength="100"
                    placeholder="Wpisz nazwę użytkownika"
                    autocomplete="off"
                  />
                  <button type="submit" :disabled="friendsLoading">Szukaj</button>
                </div>
              </form>

              <p v-if="friendsError" class="friends-status friends-error" role="alert">{{ friendsError }}</p>
              <p v-if="friendsLoading" class="friends-status" role="status">Szukanie profili...</p>
              <p v-else-if="!friendSearchSubmitted" class="friends-status">
                Wpisz nazwę użytkownika i wybierz „Szukaj”, aby znaleźć publiczny profil.
              </p>
              <p v-else-if="publicProfiles.length === 0" class="friends-status">
                Nie znaleziono pasujących publicznych profili.
              </p>
              <template v-else>
                <p class="friends-count">Publiczne profile: {{ friendsTotal }}</p>
                <ul class="friends-list">
                  <li v-for="profile in publicProfiles" :key="profile.id">
                    <img
                      v-if="profile.avatar_path"
                      class="friend-avatar"
                      :src="getPublicAvatarUrl(profile.avatar_path)"
                      alt=""
                    />
                    <span v-else class="friend-avatar friend-avatar-placeholder" aria-hidden="true">
                      {{ publicProfileName(profile).slice(0, 1).toLocaleUpperCase() }}
                    </span>
                    <div>
                      <strong>{{ publicProfileName(profile) }}</strong>
                      <span v-if="profile.city">{{ profile.city }}</span>
                    </div>
                    <RouterLink :to="{ name: 'public-profile', params: { id: profile.id } }">
                      Zobacz profil
                    </RouterLink>
                    <template v-if="getFriendState(profile.id) === 'friend'">
                      <span class="friend-badge">Znajomi</span>
                    </template>
                    <template v-else-if="getFriendState(profile.id) === 'pending_sent'">
                      <span class="pending-badge">Oczekujące</span>
                    </template>
                    <template v-else-if="getFriendState(profile.id) === 'pending_received'">
                      <span class="received-badge">Masz zaproszenie</span>
                    </template>
                    <template v-else>
                      <button
                        class="add-friend-button"
                        type="button"
                        :aria-label="`Dodaj znajomego: ${publicProfileName(profile)}`"
                        @click="sendAddFriendRequest(profile.id)"
                      >
                        Dodaj znajomego
                      </button>
                    </template>
                  </li>
                </ul>
                <nav
                  v-if="friendsLastPage > 1"
                  class="friends-pagination"
                  aria-label="Stronicowanie profili"
                >
                  <button
                    type="button"
                    :disabled="friendsCurrentPage <= 1 || friendsLoading"
                    @click="loadPublicProfiles(friendsCurrentPage - 1)"
                  >
                    Poprzednia
                  </button>
                  <span>Strona {{ friendsCurrentPage }} z {{ friendsLastPage }}</span>
                  <button
                    type="button"
                    :disabled="friendsCurrentPage >= friendsLastPage || friendsLoading"
                    @click="loadPublicProfiles(friendsCurrentPage + 1)"
                  >
                    Następna
                  </button>
                </nav>
              </template>
            </div>
          </section>
          <div v-else-if="statsLoadState === 'loading'" class="stats-message" role="status">
            Pobieranie statystyk...
          </div>
          <div v-else-if="statsLoadState === 'error'" class="stats-message" role="alert">
            <p>{{ statsError }}</p>
            <button class="retry-button" type="button" @click="loadStats">Spróbuj ponownie</button>
          </div>
          <div v-else-if="travelStats && activeTab === 'stats'" class="stats-content">
            <div class="stats-toolbar">
              <label for="stats-year">Zakres statystyk</label>
              <select id="stats-year" :value="selectedYear ?? ''" @change="selectStatsYear">
                <option value="">Cała historia</option>
                <option v-for="year in availableYears" :key="year" :value="year">
                  {{ year }}
                </option>
              </select>
              <span v-if="yearsLoading" class="filter-status" role="status">
                Ładowanie dostępnych lat...
              </span>
              <span v-else-if="yearsError" class="filter-status">
                Nie udało się wczytać listy lat.
                <button class="inline-retry-button" type="button" @click="retryAvailableYears">
                  Spróbuj ponownie
                </button>
              </span>
            </div>

            <div class="stats-grid" aria-label="Podsumowanie podróży">
              <article class="stat-card">
                <span class="stat-icon" aria-hidden="true">🧳</span>
                <strong>{{ travelStats.trips_count }}</strong>
                <span>podróży</span>
              </article>
              <article class="stat-card">
                <span class="stat-icon" aria-hidden="true">🌍</span>
                <strong>{{ travelStats.countries.length }}</strong>
                <span>odwiedzonych krajów</span>
              </article>
              <article class="stat-card">
                <span class="stat-icon" aria-hidden="true">🛣️</span>
                <strong>{{ formatDistance(travelStats.total_distance) }}</strong>
                <span>przebytych kilometrów</span>
              </article>
              <article class="stat-card">
                <span class="stat-icon" aria-hidden="true">📍</span>
                <strong>{{ travelStats.places_count }}</strong>
                <span>odwiedzonych miejsc</span>
              </article>
            </div>

            <section class="stats-section" aria-labelledby="countries-title">
              <h3 id="countries-title">Odwiedzone kraje</h3>
              <p v-if="travelStats.countries.length === 0" class="empty-state">
                Kraje pojawią się tutaj po dodaniu miejsc do podróży.
              </p>
              <ul v-else class="country-list">
                <li v-for="country in travelStats.countries" :key="country">
                  <span class="country-code">{{ country }}</span>
                  <span>{{ countryName(country) }}</span>
                </li>
              </ul>
            </section>

            <section class="stats-section" aria-labelledby="transport-title">
              <h3 id="transport-title">Dystans według transportu</h3>
              <p v-if="transportDistances.length === 0" class="empty-state">
                Dystans pojawi się po dodaniu odcinków podróży.
              </p>
              <div v-else class="transport-charts">
                <svg
                  class="transport-donut"
                  viewBox="0 0 120 120"
                  role="img"
                  aria-label="Udział środków transportu w łącznym dystansie"
                >
                  <circle class="donut-track" cx="60" cy="60" r="44" />
                  <circle
                    v-for="segment in transportChartSegments"
                    :key="segment.transport"
                    class="donut-segment"
                    cx="60"
                    cy="60"
                    r="44"
                    :style="{
                      stroke: segment.color,
                      strokeDasharray: segment.dashArray,
                      strokeDashoffset: segment.dashOffset,
                    }"
                  />
                </svg>
                <ul class="distance-legend" aria-label="Udział dystansu według transportu">
                  <li v-for="segment in transportChartSegments" :key="segment.transport">
                    <span class="legend-label">
                      <span class="legend-swatch" :style="{ backgroundColor: segment.color }" />
                      {{ transportLabels[segment.transport] ?? segment.transport }}
                    </span>
                    <strong>{{ formatDistance(segment.percentage) }}%</strong>
                  </li>
                </ul>
                <ul class="distance-chart" aria-label="Wykres dystansu według transportu">
                  <li v-for="[transport, distance] in transportDistances" :key="transport">
                    <div class="chart-row-labels">
                      <span>{{ transportLabels[transport] ?? transport }}</span>
                      <strong>{{ formatDistance(distance) }} km</strong>
                    </div>
                    <div class="chart-bar-track" aria-hidden="true">
                      <span
                        :style="{
                          width: transportBarWidth(distance),
                          backgroundColor: transportColors[transport] ?? '#386a54',
                        }"
                      />
                    </div>
                  </li>
                </ul>
              </div>
            </section>
          </div>
          <section v-else-if="travelStats && activeTab === 'badges'" class="badges-content">
            <div class="badges-heading">
              <div>
                <h3>Twoje odznaki</h3>
                <p>Odblokowuj je podczas zapisywania podróży i odkrywania nowych miejsc.</p>
              </div>
              <strong class="badge-count"
                >{{ unlockedBadgesCount }} / {{ badgeProgress.length }}</strong
              >
            </div>
            <div class="badges-grid">
              <article
                v-for="badge in badgeProgress"
                :key="badge.id"
                class="badge-card"
                :class="{ unlocked: badgeIsUnlocked(badge) }"
              >
                <span class="badge-icon" aria-hidden="true">{{ badge.icon }}</span>
                <div class="badge-details">
                  <h4>{{ badge.name }}</h4>
                  <p>{{ badge.description }}</p>
                  <div
                    class="badge-progress-track"
                    role="progressbar"
                    :aria-label="`Postęp: ${badge.name}`"
                    :aria-valuenow="badgeProgressPercent(badge)"
                    aria-valuemin="0"
                    aria-valuemax="100"
                  >
                    <span :style="{ width: `${badgeProgressPercent(badge)}%` }" />
                  </div>
                  <span class="badge-progress-label">
                    {{ badgeIsUnlocked(badge) ? 'Odblokowana' : badge.progress }}
                  </span>
                </div>
              </article>
            </div>
          </section>
        </div>
      </section>
    </div>

    <dialog
      ref="avatarDialog"
      class="avatar-dialog"
      aria-labelledby="avatar-dialog-title"
      @click.self="closeAvatarDialog"
    >
      <div class="avatar-dialog-content">
        <header class="dialog-header">
          <h2 id="avatar-dialog-title">Wybierz awatar</h2>
          <button
            class="dialog-close"
            type="button"
            aria-label="Zamknij wybór awatara"
            @click="closeAvatarDialog"
          >
            ×
          </button>
        </header>
        <div class="avatar-gallery">
          <button
            v-for="avatar in avatarFiles"
            :key="avatar"
            class="avatar-option"
            :class="{ selected: form.avatar_path === avatar }"
            type="button"
            :aria-label="`Wybierz awatar ${avatarFiles.indexOf(avatar) + 1}`"
            :aria-pressed="form.avatar_path === avatar"
            @click="selectAvatar(avatar)"
          >
            <img :src="avatarUrl(avatar)" :alt="`Awatar ${avatarFiles.indexOf(avatar) + 1}`" />
          </button>
        </div>
      </div>
    </dialog>

    <!-- Invite email preview dialog -->
    <dialog
      :open="showInvitePreview"
      class="invite-preview-dialog"
      aria-labelledby="invite-preview-title"
      @click.self="closeInvitePreview"
    >
      <div class="invite-preview-content">
        <header class="dialog-header">
          <h2 id="invite-preview-title">Podgląd zaproszenia</h2>
          <button
            class="dialog-close"
            type="button"
            aria-label="Zamknij"
            @click="closeInvitePreview"
          >
            ×
          </button>
        </header>

        <p class="invite-preview-text">
          Poniżej znajduje się treść wiadomości, która zostanie wysłana na adres
          <strong>{{ invitePreviewTo }}</strong>:
        </p>

        <div class="invite-preview-email">
          <h3 class="email-subject">🚗 Zaproszenie do Dziennika podróży</h3>
          <p class="email-body">Cześć!</p>
          <p class="email-body">
            {{ auth.user?.name }} zaprasza Cię do dołączenia do niego w aplikacji
            <strong>Dziennik podróży</strong>.
          </p>
          <div class="email-profile-info">
            <p><strong>Profil:</strong> {{ auth.user?.name }}</p>
            <p v-if="auth.user?.city"><strong>Miasto:</strong> {{ auth.user?.city }}</p>
            <p>
              <strong>Link do profilu:</strong><br>
              <a :href="inviteLink" target="_blank" rel="noopener">{{ inviteLink }}</a>
            </p>
          </div>
          <p class="email-body">
            Kliknij powyższy link, aby przejść bezpośrednio do profilu:
          </p>
          <div class="email-button-wrapper">
            <a :href="inviteLink" class="email-button" target="_blank" rel="noopener">Zobacz profil</a>
          </div>
          <p class="email-footer">
            Jeśli nie chcesz odwiedzać tego profilu, po prostu zignoruj tę wiadomość.
          </p>
        </div>

        <div class="invite-preview-actions">
          <button type="button" class="btn-cancel" @click="closeInvitePreview">Anuluj</button>
          <button type="button" class="btn-confirm" :disabled="inviteLoading" @click="confirmAndSendInvite">
            {{ inviteLoading ? 'Wysyłanie…' : 'Potwierdź i wyślij' }}
          </button>
        </div>
      </div>
    </dialog>
  </main>
</template>

<style scoped>
.profile-page {
  display: grid;
  justify-items: center;
  width: 100%;
  padding: 3rem 0;
}

.profile-layout {
  display: grid;
  grid-template-columns: minmax(30rem, 0.8fr) minmax(0, 1.4fr);
  align-items: start;
  gap: 1.5rem;
  width: 100%;
}

.profile-card {
  display: grid;
  gap: 1.5rem;
  width: 100%;
  min-width: 0;
  padding: clamp(1.5rem, 6vw, 3rem);
  border-radius: 1.5rem;
  background: linear-gradient(135deg, #e9f5ef, #f5f2e9);
  color: #111827;
}

.profile-editor-card {
  min-width: 30rem;
}

.profile-header,
.avatar-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.visibility-control {
  position: relative;
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 0.5rem;
}

.visibility-info-button {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  width: 2rem;
  height: 2rem;
  padding: 0;
  border: 1px solid #386a54;
  border-radius: 50%;
  color: #386a54;
  background: transparent;
}

.visibility-info-button svg {
  width: 1.2rem;
  height: 1.2rem;
  fill: none;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 1.8;
}

.visibility-info-button:focus-visible {
  outline: 3px solid #294d3d;
  outline-offset: 2px;
}

.visibility-info {
  position: absolute;
  z-index: 1;
  top: calc(100% + 0.5rem);
  right: 0;
  width: min(20rem, calc(100vw - 3rem));
  margin: 0;
  padding: 0.75rem 1rem;
  border: 1px solid #386a54;
  border-radius: 0.75rem;
  color: #111827;
  background: #fff;
  box-shadow: 0 0.5rem 1.5rem rgb(0 0 0 / 15%);
  font-size: 0.875rem;
  line-height: 1.5;
}

h1 {
  font-size: 2rem;
  color: inherit;
}

h2 {
  font-size: 1.5rem;
  color: inherit;
}

.avatar-preview {
  flex: 0 0 auto;
  width: 7rem;
  aspect-ratio: 1;
  overflow: hidden;
  border: 3px solid #386a54;
  border-radius: 50%;
  background: rgb(255 255 255 / 45%);
  cursor: pointer;
  padding: 0;
}

.avatar-preview:focus-visible {
  outline: 3px solid #294d3d;
  outline-offset: 3px;
}

.avatar-preview img,
.avatar-option img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-details {
  display: grid;
  flex: 1;
  gap: 0.25rem;
}

.avatar-details p {
  color: #4b5563;
  font-size: 0.875rem;
}

.choose-avatar-button {
  justify-self: start;
  padding: 0;
  border: 0;
  color: #294d3d;
  background: transparent;
  font: inherit;
  font-weight: 700;
  text-decoration: underline;
}

.profile-form,
.field {
  display: grid;
  gap: 0.5rem;
}

.profile-form {
  gap: 1.25rem;
}

.field {
  flex: 1;
  min-width: 0;
}

.field label {
  color: inherit;
}

input {
  width: 100%;
  min-height: 2.75rem;
  padding: 0.5rem 0.75rem;
  border: 1px solid #9ca3af;
  border-radius: 0.5rem;
  font: inherit;
  color: #111827;
  background: #ffffff;
}

.static-field-value {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 2.75rem;
  overflow-wrap: anywhere;
  color: #386a54;
  text-align: center;
}

.static-field-value strong {
  font-size: 20px;
  font-weight: 800;
}

.visibility-button,
.save-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  min-height: 2.75rem;
  padding: 0.6rem 0.85rem;
  border: 0;
  border-radius: 0.5rem;
  color: white;
  background: #386a54;
  font: inherit;
  font-weight: 600;
  white-space: nowrap;
}

.visibility-button svg {
  width: 1.25rem;
  height: 1.25rem;
  fill: none;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 2;
}

.avatar-visibility {
  align-self: center;
  flex: 0 0 auto;
}

.save-button {
  width: 100%;
}

.visibility-button:disabled,
.save-button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.info-row {
  display: flex;
  align-items: end;
  gap: 1rem;
}

.form-message {
  margin-bottom: -0.75rem;
}

.form-error {
  color: #b42318;
}

.form-success {
  color: #294d3d;
}

.tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  border-bottom: 1px solid var(--color-border);
}

.tabs button {
  padding: 0.65rem 0.8rem;
  border: 0;
  border-bottom: 3px solid transparent;
  color: #294d3d;
  background: transparent;
  font: inherit;
  cursor: pointer;
}

.tabs button[aria-selected='true'] {
  border-bottom-color: #386a54;
  font-weight: 700;
}

.tab-panel {
  min-height: 12rem;
  padding-top: 1rem;
}

.friends-panel {
  display: grid;
  gap: 0.75rem;
  max-width: 42rem;
}

.friends-panel > label {
  font-weight: 700;
}

.friends-search-form {
  display: grid;
  gap: 0.5rem;
}

.friends-search-form label {
  font-weight: 700;
}

.friends-search-controls {
  display: flex;
  gap: 0.5rem;
}

.friends-search-controls input {
  flex: 1;
  width: 100%;
  min-height: 2.75rem;
  padding: 0.6rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: 0.5rem;
  color: inherit;
  background: var(--color-background);
  font: inherit;
}

.friends-search-controls button,
.add-friend-button {
  padding: 0.55rem 0.8rem;
  border: 1px solid #386a54;
  border-radius: 0.5rem;
  color: white;
  background: #386a54;
  font: inherit;
  font-weight: 600;
}

.friends-search-controls button {
  cursor: pointer;
}

.friends-search-controls button:disabled,
.add-friend-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.friends-note {
  margin: 0;
  color: #64746c;
  font-size: 0.9rem;
}

.friends-note code {
  overflow-wrap: anywhere;
}

.friends-status,
.friends-count {
  margin: 0;
  color: #64746c;
}

.friends-error {
  color: #b42318;
}

.friends-error button,
.friends-pagination button {
  padding: 0.5rem 0.75rem;
  border: 1px solid #386a54;
  border-radius: 0.5rem;
  color: #294d3d;
  background: transparent;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.friends-list {
  display: grid;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.friends-list li {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: 0.75rem;
}

.friend-avatar {
  display: grid;
  flex: 0 0 2.75rem;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 50%;
}

img.friend-avatar {
  object-fit: cover;
}

.friend-avatar-placeholder {
  color: white;
  background: #386a54;
  font-weight: 700;
}

.friends-list li > div {
  display: grid;
  flex: 1;
  gap: 0.15rem;
  min-width: 0;
}

.friends-list li > div + a {
  flex: 0 0 auto;
}

.friends-list li > div span {
  color: #64746c;
  font-size: 0.9rem;
}

.friends-list a {
  color: #386a54;
  font-weight: 700;
}

.friends-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
}

.friends-pagination button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.requests-section,
.accepted-friends-section,
.trip-invitations-section,
.search-friends-section,
.invite-email-section {
  display: grid;
  gap: 0.75rem;
  padding: 1rem;
  border: 1px solid var(--color-border);
  border-radius: 0.75rem;
}

.requests-section h3,
.accepted-friends-section h3,
.trip-invitations-section h3,
.search-friends-section h3,
.invite-email-section h3 {
  margin: 0;
  font-size: 1.1rem;
}

.trip-inv-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.trip-inv-icon {
  font-size: 1.5rem;
}

.trip-inv-trip {
  display: block;
  font-size: 0.85rem;
  color: var(--color-text-muted);
}

.invite-description {
  margin: 0;
  font-size: 0.9rem;
  color: #6b7280;
}

.invite-email-form {
  display: grid;
  gap: 0.5rem;
}

.invite-email-controls {
  display: flex;
  gap: 0.5rem;
}

.invite-email-controls input {
  flex: 1;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: 0.5rem;
  font: inherit;
  font-size: 1rem;
}

.invite-email-controls button {
  padding: 0.5rem 1rem;
  border: 1px solid #386a54;
  border-radius: 0.5rem;
  color: white;
  background: #386a54;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.invite-email-controls button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.friends-success {
  color: #2f855a;
  background: rgb(47 133 90 / 12%);
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
}

.requests-tabs {
  display: flex;
  gap: 0.5rem;
}

.requests-tabs button {
  padding: 0.4rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: 0.5rem;
  background: transparent;
  color: inherit;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.requests-tabs button[aria-selected='true'] {
  border-color: #386a54;
  background: #386a54;
  color: white;
}

.requests-list {
  display: grid;
  gap: 0.5rem;
}

.accept-request-button,
.cancel-request-button {
  padding: 0.4rem 0.6rem;
  border: 1px solid #2f855a;
  border-radius: 0.5rem;
  color: white;
  background: #2f855a;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.decline-request-button {
  padding: 0.4rem 0.6rem;
  border: 1px solid #b42318;
  border-radius: 0.5rem;
  color: white;
  background: #b42318;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.remove-friend-button {
  padding: 0.4rem 0.6rem;
  border: 1px solid #64746c;
  border-radius: 0.5rem;
  color: #64746c;
  background: transparent;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.remove-friend-button:hover {
  border-color: #b42318;
  color: #b42318;
}

.friend-badge,
.pending-badge,
.received-badge {
  padding: 0.3rem 0.6rem;
  border-radius: 0.5rem;
  font-size: 0.85rem;
  font-weight: 600;
}

.friend-badge {
  color: #2f855a;
  background: rgb(47 133 90 / 12%);
}

.pending-badge {
  color: #b45309;
  background: rgb(180 83 9 / 12%);
}

.received-badge {
  color: #2563eb;
  background: rgb(37 99 235 / 12%);
}

.stats-content,
.badges-content {
  display: grid;
  gap: 1.75rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.75rem;
}

.stat-card {
  display: grid;
  justify-items: start;
  align-content: start;
  gap: 0.35rem;
  min-height: 8rem;
  padding: 1rem;
  border: 1px solid rgb(56 106 84 / 18%);
  border-radius: 1rem;
  background: rgb(255 255 255 / 65%);
}

.stat-card strong {
  font-size: clamp(1.35rem, 3vw, 2rem);
  line-height: 1.1;
  overflow-wrap: anywhere;
}

.stat-card > span:last-child,
.empty-state {
  color: #4b5563;
  font-size: 0.9rem;
}

.stat-icon {
  font-size: 1.35rem;
}

.stats-section {
  display: grid;
  gap: 0.75rem;
}

.stats-section h3,
.badges-heading h3 {
  margin: 0;
  font-size: 1.15rem;
}

.empty-state {
  margin: 0;
}

.country-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.country-list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.65rem;
  padding: 0.6rem 0.8rem;
  border: 1px solid rgb(56 106 84 / 18%);
  border-radius: 0.75rem;
  background: rgb(255 255 255 / 55%);
}

.country-code {
  display: inline-grid;
  place-items: center;
  min-width: 2rem;
  min-height: 1.5rem;
  border-radius: 0.35rem;
  color: #fff;
  background: #386a54;
  font-size: 0.75rem;
  font-weight: 700;
}

.stats-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
}

.stats-toolbar label {
  font-weight: 600;
}

.stats-toolbar select {
  min-height: 2.5rem;
  padding: 0.4rem 2rem 0.4rem 0.7rem;
  border: 1px solid #9ca3af;
  border-radius: 0.5rem;
  color: #111827;
  background: #fff;
  font: inherit;
}

.stats-toolbar select:focus-visible,
.inline-retry-button:focus-visible {
  outline: 3px solid #294d3d;
  outline-offset: 2px;
}

.filter-status {
  color: #4b5563;
  font-size: 0.875rem;
}

.inline-retry-button {
  padding: 0;
  border: 0;
  color: #294d3d;
  background: transparent;
  font: inherit;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
}

.distance-chart {
  display: grid;
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.transport-charts {
  display: grid;
  grid-template-columns: minmax(8rem, 0.45fr) minmax(0, 1fr);
  align-items: center;
  gap: 1.25rem;
}

.transport-donut {
  width: 100%;
  max-width: 13rem;
  justify-self: center;
}

.donut-track,
.donut-segment {
  fill: none;
  stroke-width: 16;
}

.donut-track {
  stroke: rgb(56 106 84 / 16%);
}

.donut-segment {
  transform: rotate(-90deg);
  transform-origin: 50% 50%;
}

.distance-legend {
  display: grid;
  gap: 0.6rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.distance-legend li,
.legend-label {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.distance-legend li {
  justify-content: space-between;
}

.legend-swatch {
  flex: 0 0 auto;
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 50%;
}

.distance-chart {
  grid-column: 1 / -1;
}

.distance-chart li {
  display: grid;
  gap: 0.4rem;
}

.chart-row-labels {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
}

.chart-row-labels strong {
  flex: 0 0 auto;
  font-variant-numeric: tabular-nums;
}

.chart-bar-track {
  height: 0.75rem;
  overflow: hidden;
  border-radius: 999px;
  background: rgb(56 106 84 / 16%);
}

.chart-bar-track span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #386a54;
}

.stats-message {
  display: grid;
  justify-items: start;
  gap: 0.75rem;
}

.retry-button {
  padding: 0.55rem 0.8rem;
  border: 0;
  border-radius: 0.5rem;
  color: #fff;
  background: #386a54;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.badges-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.badges-heading p {
  margin: 0.35rem 0 0;
  color: #4b5563;
}

.badge-count {
  flex: 0 0 auto;
  padding: 0.5rem 0.75rem;
  border-radius: 999px;
  color: #294d3d;
  background: rgb(56 106 84 / 12%);
}

.badges-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}

.badge-card {
  display: flex;
  align-items: flex-start;
  gap: 0.8rem;
  min-width: 0;
  padding: 1rem;
  border: 1px solid rgb(17 24 39 / 12%);
  border-radius: 1rem;
  background: rgb(255 255 255 / 45%);
  opacity: 0.72;
}

.badge-card.unlocked {
  border-color: rgb(56 106 84 / 40%);
  background: rgb(255 255 255 / 75%);
  opacity: 1;
}

.badge-icon {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 50%;
  background: rgb(17 24 39 / 8%);
  font-size: 1.4rem;
  filter: grayscale(1);
}

.badge-card.unlocked .badge-icon {
  background: rgb(56 106 84 / 15%);
  filter: none;
}

.badge-details {
  display: grid;
  flex: 1;
  gap: 0.35rem;
  min-width: 0;
}

.badge-details h4,
.badge-details p {
  margin: 0;
}

.badge-details p,
.badge-progress-label {
  color: #4b5563;
  font-size: 0.85rem;
}

.badge-progress-track {
  height: 0.45rem;
  overflow: hidden;
  border-radius: 999px;
  background: rgb(17 24 39 / 12%);
}

.badge-progress-track span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #386a54;
}

.avatar-dialog {
  width: min(36rem, calc(100vw - 2rem));
  max-width: none;
  max-height: calc(100vh - 2rem);
  margin: auto;
  padding: 0;
  overflow: auto;
  border: 0;
  border-radius: 1rem;
  color: #111827;
  background: #fff;
  box-shadow: 0 1.5rem 4rem rgb(0 0 0 / 25%);
}

.avatar-dialog::backdrop {
  background: rgb(17 24 39 / 65%);
}

.avatar-dialog-content {
  display: grid;
  gap: 1.5rem;
  padding: 1.5rem;
}

.dialog-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.dialog-close {
  width: 2.5rem;
  height: 2.5rem;
  border: 0;
  border-radius: 50%;
  color: inherit;
  background: #f3f4f6;
  font-size: 1.75rem;
  line-height: 1;
}

.avatar-gallery {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.75rem;
}

.avatar-option {
  aspect-ratio: 1;
  padding: 0.2rem;
  overflow: hidden;
  border: 3px solid transparent;
  border-radius: 50%;
  background: transparent;
}

.avatar-option.selected {
  border-color: #386a54;
}

/* Invite preview dialog */

.invite-preview-dialog {
  width: min(40rem, calc(100vw - 2rem));
  max-width: none;
  max-height: calc(100vh - 2rem);
  margin: auto;
  padding: 0;
  overflow: auto;
  border: 0;
  border-radius: 1rem;
  color: #111827;
  background: #fff;
  box-shadow: 0 1.5rem 4rem rgb(0 0 0 / 25%);
}

.invite-preview-dialog::backdrop {
  background: rgb(17 24 39 / 65%);
}

.invite-preview-content {
  display: grid;
  gap: 1.25rem;
  padding: 1.5rem;
}

.invite-preview-text {
  margin: 0;
  font-size: 0.95rem;
  line-height: 1.5;
  color: #374151;
}

.invite-preview-email {
  display: grid;
  gap: 0.75rem;
  padding: 1.25rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  background: #f9fafb;
}

.email-subject {
  margin: 0;
  font-size: 1.15rem;
  color: #386a54;
}

.email-body {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.6;
  color: #374151;
}

.email-profile-info {
  display: grid;
  gap: 0.4rem;
  padding: 1rem;
  border-radius: 0.75rem;
  background: #e9f5ef;
  font-size: 0.9rem;
}

.email-profile-info p {
  margin: 0;
}

.email-profile-info a {
  color: #386a54;
  word-break: break-all;
}

.email-button-wrapper {
  text-align: center;
}

.email-button {
  display: inline-block;
  padding: 0.6rem 1.25rem;
  border-radius: 0.5rem;
  color: #fff;
  background: #386a54;
  text-decoration: none;
  font-weight: 600;
}

.email-footer {
  margin: 0;
  font-size: 0.8rem;
  color: #6b7280;
}

.invite-preview-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.btn-cancel {
  padding: 0.5rem 1rem;
  border: 1px solid #9ca3af;
  border-radius: 0.5rem;
  color: #374151;
  background: #fff;
  cursor: pointer;
  font-size: 0.9rem;
}

.btn-cancel:hover {
  background: #f3f4f6;
}

.btn-confirm {
  padding: 0.5rem 1rem;
  border: 1px solid #386a54;
  border-radius: 0.5rem;
  color: #fff;
  background: #386a54;
  cursor: pointer;
  font-size: 0.9rem;
}

.btn-confirm:hover:not(:disabled) {
  background: #294d3d;
}

.btn-confirm:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 900px) {
  .profile-layout {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 700px) {
  .stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .badges-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 600px) {
  .profile-page {
    padding: 1rem 0;
  }

  .profile-editor-card {
    min-width: 0;
  }

  .profile-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .visibility-control {
    align-self: stretch;
  }

  .info-row {
    align-items: stretch;
  }

  .avatar-row {
    flex-wrap: wrap;
  }
}

@media (max-width: 420px) {
  .info-row {
    flex-direction: column;
  }

  .field-visibility {
    align-self: flex-start;
  }
}

@media (prefers-color-scheme: dark) {
  .profile-card {
    background: linear-gradient(135deg, #1a2e24, #2a2618);
    color: #f3f4f6;
  }

  .avatar-details p {
    color: #9ca3af;
  }

  .static-field-value {
    color: #6ee7b7;
  }

  .choose-avatar-button {
    color: #6ee7b7;
  }

  input {
    color: #f3f4f6;
    background: #1f2937;
    border-color: #4b5563;
  }

  .tabs {
    border-bottom-color: #374151;
  }

  .tabs button {
    color: #9ca3af;
  }

  .tabs button:hover {
    color: #f3f4f6;
  }

  .tabs button[aria-selected='true'] {
    color: #6ee7b7;
    border-bottom-color: #6ee7b7;
  }

  .tab-panel p {
    color: #9ca3af;
  }

  .stat-card,
  .country-list li,
  .badge-card {
    border-color: rgb(156 163 175 / 25%);
    background: rgb(31 41 55 / 65%);
  }

  .stat-card > span:last-child,
  .empty-state,
  .filter-status,
  .badges-heading p,
  .badge-details p,
  .badge-progress-label {
    color: #9ca3af;
  }

  .badge-count {
    color: #6ee7b7;
    background: rgb(110 231 183 / 12%);
  }

  .badge-progress-track {
    background: rgb(156 163 175 / 25%);
  }

  .badge-progress-track span {
    background: #6ee7b7;
  }

  .stats-toolbar select {
    border-color: #4b5563;
    color: #f3f4f6;
    background: #1f2937;
  }

  .inline-retry-button {
    color: #6ee7b7;
  }

  .chart-bar-track {
    background: rgb(156 163 175 / 25%);
  }

  .donut-track {
    stroke: rgb(156 163 175 / 25%);
  }

  .chart-bar-track span {
    background: #6ee7b7;
  }

  .form-error {
    color: #fca5a5;
  }

  .form-success {
    color: #6ee7b7;
  }

  .avatar-dialog {
    color: #f3f4f6;
    background: #1f2937;
  }

  .visibility-info-button {
    border-color: #6ee7b7;
    color: #6ee7b7;
  }

  .visibility-info-button:focus-visible {
    outline-color: #6ee7b7;
  }

  .visibility-info {
    border-color: #6ee7b7;
    color: #f3f4f6;
    background: #1f2937;
  }

  .dialog-close {
    background: #374151;
  }

  .invite-preview-dialog {
    color: #f3f4f6;
    background: #1f2937;
  }

  .invite-preview-text {
    color: #d1d5db;
  }

  .invite-preview-email {
    border-color: #374151;
    background: #111827;
  }

  .email-subject {
    color: #6ee7b7;
  }

  .email-body {
    color: #d1d5db;
  }

  .email-profile-info {
    background: rgb(110 231 183 / 12%);
  }

  .email-profile-info a {
    color: #6ee7b7;
  }

  .email-button {
    background: #6ee7b7;
    color: #111827;
  }

  .email-footer {
    color: #9ca3af;
  }

  .btn-cancel {
    border-color: #6b7280;
    color: #d1d5db;
    background: #1f2937;
  }

  .btn-cancel:hover {
    background: #374151;
  }

  .btn-confirm {
    border-color: #6ee7b7;
    background: #6ee7b7;
    color: #111827;
  }

  .btn-confirm:hover:not(:disabled) {
    background: #386a54;
    color: #fff;
  }
}
</style>
