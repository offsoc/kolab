<template>
    <list-table :list="list" :setup="setup"></list-table>
</template>

<script>
    import { ListTable } from '../Widgets/ListTools'
    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faGlobe').definition,
    )

    export default {
        components: {
            ListTable
        },
        props: {
            list: { type: Array, default: () => [] }
        },
        data() {
            return {
                setup: {
                    model: 'domain',
                    columns: [
                        {
                            prop: 'namespace',
                            icon: 'globe',
                            link: true
                        }
                    ]
                }
            }
        }
    }
</script>
