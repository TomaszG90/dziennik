
package pl.dziennikpodrozy.mobile.data.remote

sealed class ApiException(
    message: String,
    val statusCode: Int
) : Exception(message) {

    class Unauthorized(message: String, statusCode: Int) :
        ApiException(message, statusCode)

    class Forbidden(message: String, statusCode: Int) :
        ApiException(message, statusCode)

    class NotFound(message: String, statusCode: Int) :
        ApiException(message, statusCode)

    class Validation(
        message: String,
        statusCode: Int,
        val fieldErrors: Map<String, List<String>>
    ) : ApiException(message, statusCode)

    class ServerError(message: String, statusCode: Int) :
        ApiException(message, statusCode)

    class Unknown(message: String, statusCode: Int) :
        ApiException(message, statusCode)
}

object ApiExceptionMapper {

    fun map(statusCode: Int, error: ApiError?): ApiException {
        val message = error?.message ?: "Wystąpił nieoczekiwany błąd."

        return when (statusCode) {
            401 -> ApiException.Unauthorized(message, statusCode)
            403 -> ApiException.Forbidden(message, statusCode)
            404 -> ApiException.NotFound(message, statusCode)

            422 -> ApiException.Validation(
                message = message,
                statusCode = statusCode,
                fieldErrors = error?.errors ?: emptyMap()
            )

            in 500..599 -> ApiException.ServerError(message, statusCode)

            else -> ApiException.Unknown(message, statusCode)
        }
    }
}
