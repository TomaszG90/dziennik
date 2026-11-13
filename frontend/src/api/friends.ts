import { apiClient } from './client'
import type { PublicProfile } from './publicProfiles'

export interface Friend {
  id: number
  name?: string
  avatar_path?: string
  created_at: string
}

export interface FriendRequest {
  id: number
  status: 'pending' | 'accepted' | 'declined'
  sender_id: number
  recipient_id: number
  user: PublicProfile
  created_at: string
}

export async function getFriends(): Promise<Friend[]> {
  const response = await apiClient.get<{ data: Friend[] }>('/friends')
  return response.data.data
}

export async function removeFriend(userId: number): Promise<void> {
  await apiClient.delete(`/friends/${userId}`)
}

export async function getFriendRequests(
  direction: 'incoming' | 'outgoing',
): Promise<FriendRequest[]> {
  const response = await apiClient.get<{ data: FriendRequest[] }>('/friend-requests', {
    params: { direction },
  })
  return response.data.data
}

export async function sendFriendRequest(userId: number): Promise<FriendRequest> {
  const response = await apiClient.post<{ data: FriendRequest }>('/friend-requests', {
    user_id: userId,
  })
  return response.data.data
}

export async function acceptFriendRequest(requestId: number): Promise<FriendRequest> {
  const response = await apiClient.patch<{ data: FriendRequest }>(
    `/friend-requests/${requestId}`,
    { action: 'accept' },
  )
  return response.data.data
}

export async function declineFriendRequest(requestId: number): Promise<FriendRequest> {
  const response = await apiClient.patch<{ data: FriendRequest }>(
    `/friend-requests/${requestId}`,
    { action: 'decline' },
  )
  return response.data.data
}

export async function cancelFriendRequest(requestId: number): Promise<void> {
  await apiClient.delete(`/friend-requests/${requestId}`)
}

export async function sendInviteEmail(email: string): Promise<{ message: string }> {
  const response = await apiClient.post<{ message: string }>('/invite', { email })
  return response.data
}

// ── Trip Invitations ──

export interface TripInvitation {
  id: number
  trip_id: number
  inviter_id: number
  invitee_id: number
  status: 'pending' | 'accepted' | 'declined'
  invitee?: PublicProfile
  inviter?: PublicProfile
  created_at: string
}

export async function getTripInvitations(tripId: number): Promise<TripInvitation[]> {
  const response = await apiClient.get<{ data: TripInvitation[] }>(`/trips/${tripId}/invitations`)
  return response.data.data
}

export async function inviteToTrip(tripId: number, userId: number): Promise<TripInvitation> {
  const response = await apiClient.post<{ data: TripInvitation }>(`/trips/${tripId}/invitations`, {
    user_id: userId,
  })
  return response.data.data
}

export async function acceptTripInvitation(
  tripId: number,
  invitationId: number,
): Promise<TripInvitation> {
  const response = await apiClient.patch<{ data: TripInvitation }>(
    `/trips/${tripId}/invitations/${invitationId}`,
    { action: 'accept' },
  )
  return response.data.data
}

export async function declineTripInvitation(
  tripId: number,
  invitationId: number,
): Promise<TripInvitation> {
  const response = await apiClient.patch<{ data: TripInvitation }>(
    `/trips/${tripId}/invitations/${invitationId}`,
    { action: 'decline' },
  )
  return response.data.data
}

export async function cancelTripInvitation(tripId: number, invitationId: number): Promise<void> {
  await apiClient.delete(`/trips/${tripId}/invitations/${invitationId}`)
}

export async function getMyTripInvitations(): Promise<TripInvitation[]> {
  const response = await apiClient.get<{ data: TripInvitation[] }>('/trip-invitations')
  return response.data.data
}
