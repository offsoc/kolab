<template>
    <div v-if="folder.id" class="container">
        <div class="card" id="folder-info">
            <div class="card-body">
                <div class="card-title">{{ folder.email }}</div>
                <div class="card-text">
                    <form class="read-only short">
                        <div class="row plaintext">
                            <label for="folderid" class="col-sm-4 col-form-label">
                                {{ $t('form.id') }} <span class="text-muted">({{ $t('form.created') }})</span>
                            </label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="folderid">
                                    {{ folder.id }} <span class="text-muted">({{ folder.created_at }})</span>
                                </span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="status" class="col-sm-4 col-form-label">{{ $t('form.status') }}</label>
                            <div class="col-sm-8">
                                <span :class="$root.statusClass(folder) + ' form-control-plaintext'" id="status">{{ $root.statusText(folder) }}</span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="name" class="col-sm-4 col-form-label">{{ $t('form.name') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="name">{{ folder.name }}</span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="type" class="col-sm-4 col-form-label">{{ $t('form.type') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="type">{{ $t('shf.type-' + folder.type) }}</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <tabs class="mt-3" :tabs="tabs" ref="tabs"></tabs>
        <div class="tab-content">
            <div class="tab-pane show active" id="settings" role="tabpanel" aria-labelledby="tab-settings">
                <div class="card-body">
                    <div class="card-text">
                        <form class="read-only short">
                            <div class="row plaintext">
                                <label for="acl" class="col-sm-4 col-form-label">{{ $t('form.acl') }}</label>
                                <div class="col-sm-8">
                                    <span class="form-control-plaintext" id="acl">
                                        <span v-if="folder.config.acl.length">
                                            <span v-for="(entry, index) in folder.config.acl" :key="index">
                                                {{ entry.replace(',', ':') }}<br>
                                            </span>
                                        </span>
                                        <span v-else>{{ $t('form.none') }}</span>
                                    </span>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="aliases" role="tabpanel" aria-labelledby="tab-aliases">
                <div class="card-body">
                    <div class="card-text">
                        <list-table :list="folder.aliases" :setup="aliasesListSetup" class="mb-0"></list-table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { ListTable } from '../Widgets/ListTools'

    export default {
        components: {
            ListTable
        },
        data() {
            return {
                aliasesListSetup: {
                    columns: [
                        {
                            prop: 'email',
                            content: item => item
                        },
                    ],
                    footLabel: 'shf.aliases-none'
                },
                folder: { config: {}, aliases: [] },
                tabs: [
                    { label: 'form.settings' },
                    { label: 'user.email-aliases', count: 0 }
                ]
            }
        },
        created() {
            axios.get('/api/v4/shared-folders/' + this.$route.params.folder, { loader: true })
                .then(response => {
                    this.folder = response.data
                    this.tabs[1].count = this.folder.aliases.length
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
