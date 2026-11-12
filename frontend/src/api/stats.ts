import { apiClient } from './client'
import type { StatsIndexResponses } from './generated/types.gen'

export type TravelStats = StatsIndexResponses[200]['data']

export async function getTravelStats(year?: number): Promise<TravelStats> {
  const { data } = await apiClient.get<{ data: TravelStats }>(
    '/stats',
    year === undefined ? undefined : { params: { year } },
  )

  return data.data
}
