<template>
    <div class="container">
        <div class="card" id="user-delete">
            <div class="card-body">
                <div class="card-title">{{ $t('user.delete-account') }}</div>
                <div class="card-text">
                    <p>{{ $t('user.profile-delete-text1') }} <strong>{{ $t('user.profile-delete-warning') }}</strong>.</p>
                    <p>{{ $t('user.profile-delete-text2') }}</p>
                    <p v-if="supportEmail" v-html="$t('user.profile-delete-support', { href: 'mailto:' + supportEmail, email: supportEmail })"></p>
                    <p>{{ $t('user.profile-delete-contact', { app: $root.appName }) }}</p>
                    <p class="buttons">
                        <btn class="btn-secondary button-cancel" @click="$router.go(-1)">{{ $t('btn.cancel') }}</btn>
                        <btn class="btn-danger button-delete" @click="deleteProfile" icon="trash-can">{{ $t('user.profile-delete') }}</btn>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                supportEmail: window.config['app.support_email']
            }
        },
        created() {
            if (!this.$root.isController(this.$root.authInfo.wallet.id)) {
                this.$root.errorPage(403)
            }
        },
        mounted() {
            $('button.btn-secondary').focus()
        },
        methods: {
            deleteProfile() {
                axios.delete('/api/v4/users/' + this.$root.authInfo.id)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$root.logoutUser()
                            this.$toast.success(response.data.message)
                        }
                    })
            }
        }
    }
</script>
