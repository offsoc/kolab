<template>
    <div class="container">
        <div class="card" id="user-delete">
            <div class="card-body">
                <div class="card-title">Delete this account?</div>
                <div class="card-text">
                    <p>This will delete the account as well as all domains, users and aliases associated with this account.
                        <strong>This operation is irreversible</strong>.</p>
                    <p>As you will not be able to recover anything after this point, please make sure
                        that you have migrated all data before proceeding.</p>
                    <p>As we always strive to improve, we would like to ask for 2 minutes of your time.
                        The best tool for improvement is feedback from users, and we would like to ask
                        for a few words about your reasons for leaving our service. Please send your feedback
                        to support@kolabnow.com.</p>
                    <p>Also feel free to contact Kolab Now Support at support@kolabnow.com with any questions
                        or concerns that you may have in this context.</p>
                    <button class="btn btn-secondary button-cancel" @click="$router.go(-1)">Cancel</button>
                    <button class="btn btn-danger button-delete" @click="deleteProfile">Delete account</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        created() {
            if (!this.$root.isController(this.$store.state.authInfo.wallet.id)) {
                this.$root.errorPage(403)
            }
        },
        mounted() {
            $('button.btn-secondary').focus()
        },
        methods: {
            deleteProfile() {
                axios.delete('/api/v4/users/' + this.$store.state.authInfo.id)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$root.logoutUser()
                            this.$toastr('success', response.data.message)
                        }
                    })
            }
        }
    }
</script>
