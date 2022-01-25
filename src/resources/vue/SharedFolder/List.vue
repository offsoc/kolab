<template>
    <div class="container">
        <div class="card" id="folder-list">
            <div class="card-body">
                <div class="card-title">
                    {{ $tc('shf.list-title', 2) }}
                    <small><sup class="badge bg-primary">{{ $t('dashboard.beta') }}</sup></small>
                    <router-link v-if="!$root.isDegraded()" class="btn btn-success float-end create-folder" :to="{ path: 'shared-folder/new' }" tag="button">
                        <svg-icon icon="cog"></svg-icon> {{ $t('shf.create') }}
                    </router-link>
                </div>
                <div class="card-text">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th scope="col">{{ $t('form.name') }}</th>
                                <th scope="col">{{ $t('form.type') }}</th>
                                <th scope="col">{{ $t('form.email') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="folder in folders" :key="folder.id" @click="$root.clickRecord">
                                <td>
                                    <svg-icon icon="folder-open" :class="$root.statusClass(folder)" :title="$root.statusText(folder)"></svg-icon>
                                    <router-link :to="{ path: 'shared-folder/' + folder.id }">{{ folder.name }}</router-link>
                                </td>
                                <td>{{ $t('shf.type-' + folder.type) }}</td>
                                <td><router-link :to="{ path: 'shared-folder/' + folder.id }">{{ folder.email }}</router-link></td>
                            </tr>
                        </tbody>
                        <tfoot class="table-fake-body">
                            <tr>
                                <td colspan="3">{{ $t('shf.list-empty') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                folders: []
            }
        },
        created() {
            this.$root.startLoading()

            axios.get('/api/v4/shared-folders')
                .then(response => {
                    this.$root.stopLoading()
                    this.folders = response.data
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
