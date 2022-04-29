<template>
    <div class="container">
        <div class="card" id="files">
            <div class="card-body">
                <div class="card-title">
                    {{ $t('dashboard.files') }}
                    <small><sup class="badge bg-primary">{{ $t('dashboard.beta') }}</sup></small>
                    <div id="drop-area" class="file-drop-area float-end">
                        <svg-icon icon="upload"></svg-icon> Click or drop file(s) here
                    </div>
                </div>
                <div class="card-text pt-4">
                    <div class="mb-2 d-flex w-100">
                        <list-search :placeholder="$t('file.search')" :on-search="searchFiles"></list-search>
                    </div>
                    <table class="table table-sm table-hover files">
                        <thead>
                            <tr>
                                <th scope="col" class="name">{{ $t('form.name') }}</th>
                                <th scope="col" class="buttons"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="file in files" :key="file.id" @click="$root.clickRecord">
                                <td class="name">
                                    <svg-icon icon="file"></svg-icon>
                                    <router-link :to="{ path: 'file/' + file.id }">{{ file.name }}</router-link>
                                </td>
                                <td class="buttons">
                                    <btn class="button-download p-0 ms-1" @click="fileDownload(file)" icon="download" :title="$t('btn.download')"></btn>
                                    <btn class="button-delete text-danger p-0 ms-1" @click="fileDelete(file)" icon="trash-can" :title="$t('btn.delete')"></btn>
                                </td>
                            </tr>
                        </tbody>
                        <list-foot :colspan="2" :text="$t('file.list-empty')"></list-foot>
                    </table>
                    <list-more v-if="hasMore" :on-click="loadFiles"></list-more>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import FileAPI from '../../js/files.js'
    import ListTools from '../Widgets/ListTools'

    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faFile').definition,
        require('@fortawesome/free-solid-svg-icons/faDownload').definition,
        require('@fortawesome/free-solid-svg-icons/faUpload').definition,
    )

    export default {
        mixins: [ ListTools ],
        data() {
            return {
                api: {},
                files: []
            }
        },
        mounted() {
            this.uploads = {}

            this.api = new FileAPI({
                    dropArea: '#drop-area',
                    eventHandler: this.eventHandler
            })

            this.loadFiles({ init: true })
        },
        methods: {
            eventHandler(name, params) {
                const camelCase = name.toLowerCase().replace(/[^a-zA-Z0-9]+(.)/g, (m, chr) => chr.toUpperCase())
                const method = camelCase + 'Handler'

                if (method in this) {
                    this[method](params)
                }
            },
            fileDelete(file) {
                axios.delete('api/v4/files/' + file.id)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            // Refresh the list
                            this.loadFiles({ reset: true })
                        }
                    })
            },
            fileDownload(file) {
                // This is not an appropriate method for big files, we can consider
                // using it still for very small files.
                // this.$root.downloadFile('api/v4/files/' + file.id + '?download=1', file.name)

                // This method first makes a request to the API to get the download URL (which does not
                // require authentication) and then use it to download the file.
                this.api.fileDownload(file.id)
            },
            loadFiles(params) {
                this.listSearch('files', 'api/v4/files', params)
            },
            searchFiles(search) {
                this.loadFiles({ reset: true, search })
            },
            uploadProgressHandler(params) {
                // Note: There might be more than one event with completed=0
                // e.g. if you upload multiple files at once
                if (params.completed == 0 && !(params.id in this.uploads)) {
                    // Create the toast message with progress bar
                    this.uploads[params.id] = this.$toast.message({
                        icon: 'upload',
                        timeout: 24 * 60 * 60 * 60 * 1000,
                        title: this.$t('msg.uploading'),
                        msg: `${params.name} (${this.api.sizeText(params.total)})`,
                        progress: 0
                    })
                } else if (params.id in this.uploads) {
                    if (params.completed == 100) {
                        this.uploads[params.id].delete() // close the toast message
                        delete this.uploads[params.id]

                        // TODO: Reloading the list is probably not the best solution
                        this.loadFiles({ reset: true })
                    } else {
                        // update progress bar
                        this.uploads[params.id].updateProgress(params.completed)
                    }
                }
            }
        }
    }
</script>
