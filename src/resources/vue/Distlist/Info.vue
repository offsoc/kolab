<template>
    <div class="container">
        <status-component v-if="list_id !== 'new'" :status="status" @status-update="statusUpdate"></status-component>

        <div class="card" id="distlist-info">
            <div class="card-body">
                <div class="card-title" v-if="list_id !== 'new'">
                    Distribution list
                    <button class="btn btn-outline-danger button-delete float-right" @click="deleteList()" tag="button">
                        <svg-icon icon="trash-alt"></svg-icon> Delete list
                    </button>
                </div>
                <div class="card-title" v-if="list_id === 'new'">New distribution list</div>
                <div class="card-text">
                    <form @submit.prevent="submit">
                        <div v-if="list_id !== 'new'" class="form-group row plaintext">
                            <label for="status" class="col-sm-4 col-form-label">Status</label>
                            <div class="col-sm-8">
                                <span :class="$root.distlistStatusClass(list) + ' form-control-plaintext'" id="status">{{ $root.distlistStatusText(list) }}</span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="email" class="col-sm-4 col-form-label">Email</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="email" :disabled="list_id !== 'new'" required v-model="list.email">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="members-input" class="col-sm-4 col-form-label">Recipients</label>
                            <div class="col-sm-8">
                                <list-input id="members" :list="list.members"></list-input>
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit"><svg-icon icon="check"></svg-icon> Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import ListInput from '../Widgets/ListInput'
    import StatusComponent from '../Widgets/Status'

    export default {
        components: {
            ListInput,
            StatusComponent
        },
        data() {
            return {
                list_id: null,
                list: { members: [] },
                status: {}
            }
        },
        created() {
            this.list_id = this.$route.params.list

            if (this.list_id != 'new') {
                this.$root.startLoading()

                axios.get('/api/v4/groups/' + this.list_id)
                    .then(response => {
                        this.$root.stopLoading()
                        this.list = response.data
                        this.status = response.data.statusInfo
                    })
                    .catch(this.$root.errorHandler)
            }
        },
        methods: {
            deleteList() {
                axios.delete('/api/v4/groups/' + this.list_id)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.$router.push({ name: 'distlists' })
                        }
                    })
            },
            statusUpdate(list) {
                this.list = Object.assign({}, this.list, list)
            },
            submit() {
                this.$root.clearFormValidation($('#list-info form'))

                let method = 'post'
                let location = '/api/v4/groups'

                if (this.list_id !== 'new') {
                    method = 'put'
                    location += '/' + this.list_id
                }

                axios[method](location, this.list)
                    .then(response => {
                        this.$toast.success(response.data.message)
                        this.$router.push({ name: 'distlists' })
                    })
            }
        }
    }
</script>
