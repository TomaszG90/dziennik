
package pl.dziennikpodrozy.mobile

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import pl.dziennikpodrozy.mobile.data.remote.ApiError
import pl.dziennikpodrozy.mobile.data.remote.ApiException
import pl.dziennikpodrozy.mobile.data.remote.ApiExceptionMapper

class ApiExceptionMapperTest {

    @Test
    fun `maps 401 to Unauthorized`() {
        val result = ApiExceptionMapper.map(
            401,
            ApiError(message = "Unauthenticated.")
        )

        assertTrue(result is ApiException.Unauthorized)
        assertEquals(401, result.statusCode)
    }

    @Test
    fun `maps 403 to Forbidden`() {
        val result = ApiExceptionMapper.map(
            403,
            ApiError(message = "Forbidden.")
        )

        assertTrue(result is ApiException.Forbidden)
        assertEquals(403, result.statusCode)
    }

    @Test
    fun `maps 404 to NotFound`() {
        val result = ApiExceptionMapper.map(
            404,
            ApiError(message = "Not found.")
        )

        assertTrue(result is ApiException.NotFound)
        assertEquals(404, result.statusCode)
    }

    @Test
    fun `maps 422 to Validation with field errors`() {
        val errors = mapOf(
            "email" to listOf("The email field is required."),
            "password" to listOf("The password field is required.")
        )

        val result = ApiExceptionMapper.map(
            422,
            ApiError(
                message = "The given data was invalid.",
                errors = errors
            )
        )

        assertTrue(result is ApiException.Validation)

        result as ApiException.Validation

        assertEquals(422, result.statusCode)
        assertEquals(errors, result.fieldErrors)
    }

    @Test
    fun `maps 500 to ServerError`() {
        val result = ApiExceptionMapper.map(
            500,
            ApiError(message = "Server error.")
        )

        assertTrue(result is ApiException.ServerError)
        assertEquals(500, result.statusCode)
    }

    @Test
    fun `maps unknown status to Unknown`() {
        val result = ApiExceptionMapper.map(
            418,
            ApiError(message = "Unexpected response.")
        )

        assertTrue(result is ApiException.Unknown)
        assertEquals(418, result.statusCode)
    }
}
