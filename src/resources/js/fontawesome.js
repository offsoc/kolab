
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
//import { } from '@fortawesome/free-brands-svg-icons'
import {
    faCheckSquare,
    faCreditCard,
    faSquare,
} from '@fortawesome/free-regular-svg-icons'

import {
    faCheck,
    faCheckCircle,
    faComments,
    faDownload,
    faGlobe,
    faExclamationCircle,
    faInfoCircle,
    faLock,
    faKey,
    faPlus,
    faSearch,
    faSignInAlt,
    faSyncAlt,
    faTrashAlt,
    faUser,
    faUserCog,
    faUsers,
    faWallet
} from '@fortawesome/free-solid-svg-icons'

// Register only these icons we need
library.add(
    faCheck,
    faCheckCircle,
    faCheckSquare,
    faComments,
    faCreditCard,
    faDownload,
    faExclamationCircle,
    faGlobe,
    faInfoCircle,
    faLock,
    faKey,
    faPlus,
    faSearch,
    faSignInAlt,
    faSquare,
    faSyncAlt,
    faTrashAlt,
    faUser,
    faUserCog,
    faUsers,
    faWallet
)

export default FontAwesomeIcon
