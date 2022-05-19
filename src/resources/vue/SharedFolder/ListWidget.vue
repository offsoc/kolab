<template>
    <list-table :list="list" :setup="setup"></list-table>
</template>

<script>
    import { ListTable } from '../Widgets/ListTools'
    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faFolderOpen').definition,
    )

    export default {
        components: {
            ListTable
        },
        props: {
            withEmail: { type: Boolean, default: () => false },
            list: { type: Array, default: () => [] }
        },
        computed: {
            setup() {
                let columns = [
                    {
                        prop: 'name',
                        icon: 'folder-open',
                        link: true
                    },
                    {
                        prop: 'type',
                        contentLabel: item => 'shf.type-' + item.type
                    }
                ]

                if (this.withEmail) {
                    columns.push({ prop: 'email', link: true })
                }

                return {
                    columns,
                    model: 'shared-folder',
                    prefix: 'shf'
                }
            }
        }
    }
</script>
