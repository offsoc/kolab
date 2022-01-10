<template>
    <div v-if="list.id" class="container">
        <div class="card" id="distlist-info">
            <div class="card-body">
                <div class="card-title">{{ list.email }}</div>
                <div class="card-text">
                    <form class="read-only short">
                        <div class="row plaintext">
                            <label for="distlistid" class="col-sm-4 col-form-label">
                                {{ $t('form.id') }} <span class="text-muted">({{ $t('form.created') }})</span>
                            </label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="distlistid">
                                    {{ list.id }} <span class="text-muted">({{ list.created_at }})</span>
                                </span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="status" class="col-sm-4 col-form-label">{{ $t('form.status') }}</label>
                            <div class="col-sm-8">
                                <span :class="$root.statusClass(list) + ' form-control-plaintext'" id="status">{{ $root.statusText(list) }}</span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="name" class="col-sm-4 col-form-label">{{ $t('distlist.name') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="name">{{ list.name }}</span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="members" class="col-sm-4 col-form-label">{{ $t('distlist.recipients') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="members">
                                    <span v-for="member in list.members" :key="member">{{ member }}<br></span>
                                </span>
                            </div>
                        </div>
                    </form>
                    <div class="mt-2 buttons">
                        <button v-if="!list.isSuspended" id="button-suspend" class="btn btn-warning" type="button" @click="suspendList">
                            {{ $t('btn.suspend') }}
                        </button>
                        <button v-if="list.isSuspended" id="button-unsuspend" class="btn btn-warning" type="button" @click="unsuspendList">
                            {{ $t('btn.unsuspend') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <ul class="nav nav-tabs mt-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tab-settings" href="#distlist-settings" role="tab" aria-controls="distlist-settings" aria-selected="false" @click="$root.tab">
                    {{ $t('form.settings') }}
                </a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane show active" id="distlist-settings" role="tabpanel" aria-labelledby="tab-settings">
                <div class="card-body">
                    <div class="card-text">
                        <form class="read-only short">
                            <div class="row plaintext">
                                <label for="sender_policy" class="col-sm-4 col-form-label">{{ $t('distlist.sender-policy') }}</label>
                                <div class="col-sm-8">
                                    <span class="form-control-plaintext" id="sender_policy">
                                        {{ list.config.sender_policy && list.config.sender_policy.length ? list.config.sender_policy.join(', ') : $t('form.none') }}
                                    </span>
                                </div>
                            </div>
                        </form>
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
                list: { members: [], config: {} }
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
