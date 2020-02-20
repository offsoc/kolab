<template>
    <div class="container">
        <div class="card" id="user-info">
            <div class="card-body">
                <div class="card-title">User account</div>
                <div class="card-text">
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                user_id: null,
                user: null
            }
        },
        created() {
            if (this.user_id = this.$route.params.user) {
                axios.get('/api/v4/users/' + this.user_id)
                    .then(response => {
                        this.user = response.data
                    })
                    .catch(this.$root.errorHandler)
            } else {
                this.$root.errorPage(404)
            }
        }
    }
</script>
