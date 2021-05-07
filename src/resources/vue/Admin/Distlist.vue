<template>
    <div v-if="list.id" class="container">
        <div class="card" id="distlist-info">
            <div class="card-body">
                <div class="card-title">{{ list.email }}</div>
                <div class="card-text">
                    <form class="read-only short">
                        <div class="form-group row">
                            <label for="distlistid" class="col-sm-4 col-form-label">ID <span class="text-muted">(Created at)</span></label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="distlistid">
                                    {{ list.id }} <span class="text-muted">({{ list.created_at }})</span>
                                </span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="status" class="col-sm-4 col-form-label">Status</label>
                            <div class="col-sm-8">
                                <span :class="$root.distlistStatusClass(list) + ' form-control-plaintext'" id="status">{{ $root.distlistStatusText(list) }}</span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="members-input" class="col-sm-4 col-form-label">Recipients</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="members">
                                    <span v-for="member in list.members" :key="member">{{ member }}<br></span>
                                </span>
                            </div>
                        </div>
                    </form>
                    <div class="mt-2">
                        <button v-if="!list.isSuspended" id="button-suspend" class="btn btn-warning" type="button" @click="suspendList">Suspend</button>
                        <button v-if="list.isSuspended" id="button-unsuspend" class="btn btn-warning" type="button" @click="unsuspendList">Unsuspend</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                list: { members: [] }
            }
        },
        created() {
            this.$root.startLoading()

            axios.get('/api/v4/groups/' + this.$route.params.list)
                .then(response => {
                    this.$root.stopLoading()
                    this.list = response.data
                })
                .catch(this.$root.errorHandler)
        },
        methods: {
            suspendList() {
                axios.post('/api/v4/groups/' + this.list.id + '/suspend', {})
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.list = Object.assign({}, this.list, { isSuspended: true })
                        }
                    })
            },
            unsuspendList() {
                axios.post('/api/v4/groups/' + this.list.id + '/unsuspend', {})
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.list = Object.assign({}, this.list, { isSuspended: false })
                        }
                    })
            }
        }
    }
</script>
