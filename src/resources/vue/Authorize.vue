<template>
    <div class="container">
    </div>
</template>

<script>
    export default {
        created() {
            // Just auto approve for now
            // If we wanted we could use this page to list what is being authorized,
            // and allow the user to approve/reject.
            // Note that in case of SSO it is also expected that there's no user interaction at all,
            // isn't it? Maybe we should show the details once per client_id+user_id combination.
            this.submitApproval()
        },
        methods: {
            submitApproval() {
                let props = ['client_id', 'redirect_uri', 'state', 'nonce', 'scope', 'response_type', 'response_mode']
                let post = this.$root.pick(this.$route.query, props)

                axios.post('/api/oauth/approve', post, { loading: true })
                    .then(response => {
                        // Follow the redirect to the external page
                        window.location.href = response.data.redirectUrl;
                    })
                    .catch(this.$root.errorHandler)
            }
        }
    }
</script>

