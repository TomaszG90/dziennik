
package pl.dziennikpodrozy.mobile.data.session

import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import java.security.KeyStore
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

class SecureTokenStorage(context: Context) {

    private val preferences = context.applicationContext
        .getSharedPreferences("secure_session", Context.MODE_PRIVATE)

    private val keyAlias = "dziennik_token_key"

    fun saveToken(token: String) {
        require(token.isNotBlank()) { "Token nie może być pusty." }

        val cipher = Cipher.getInstance(TRANSFORMATION)
        cipher.init(Cipher.ENCRYPT_MODE, getOrCreateKey())

        val encryptedToken = cipher.doFinal(token.toByteArray(Charsets.UTF_8))

        val combined = cipher.iv + encryptedToken

        val encoded = Base64.encodeToString(combined, Base64.NO_WRAP)

        check(preferences.edit().putString(TOKEN_KEY, encoded).commit()) {
            "Nie udało się zapisać tokenu."
        }
    }

    fun getToken(): String? {
        val encoded = preferences.getString(TOKEN_KEY, null) ?: return null

        return try {
            val combined = Base64.decode(encoded, Base64.NO_WRAP)

            require(combined.size > IV_LENGTH) {
                "Nieprawidłowe dane zaszyfrowanego tokenu."
            }

            val iv = combined.copyOfRange(0, IV_LENGTH)
            val encryptedToken = combined.copyOfRange(IV_LENGTH, combined.size)

            val cipher = Cipher.getInstance(TRANSFORMATION)
            cipher.init(
                Cipher.DECRYPT_MODE,
                getOrCreateKey(),
                GCMParameterSpec(TAG_LENGTH, iv)
            )

            cipher.doFinal(encryptedToken).toString(Charsets.UTF_8)
        } catch (exception: Exception) {
            clearToken()
            null
        }
    }

    fun clearToken() {
        check(preferences.edit().remove(TOKEN_KEY).commit()) {
            "Nie udało się usunąć tokenu."
        }
    }

    private fun getOrCreateKey(): SecretKey {
        val keyStore = KeyStore.getInstance(ANDROID_KEYSTORE).apply {
            load(null)
        }

        val existingKey = keyStore.getKey(keyAlias, null) as? SecretKey

        if (existingKey != null) {
            return existingKey
        }

        val keyGenerator = KeyGenerator.getInstance(
            KeyProperties.KEY_ALGORITHM_AES,
            ANDROID_KEYSTORE
        )

        keyGenerator.init(
            KeyGenParameterSpec.Builder(
                keyAlias,
                KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT
            )
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .setKeySize(256)
                .setRandomizedEncryptionRequired(true)
                .build()
        )

        return keyGenerator.generateKey()
    }

    companion object {
        private const val TRANSFORMATION = "AES/GCM/NoPadding"
        private const val ANDROID_KEYSTORE = "AndroidKeyStore"
        private const val TOKEN_KEY = "encrypted_token"
        private const val IV_LENGTH = 12
        private const val TAG_LENGTH = 128
    }
}
