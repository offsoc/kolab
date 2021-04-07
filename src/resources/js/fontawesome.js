
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
    faEnvelope,
    faGlobe,
    faUniversity,
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

import {
    faPaypal
} from '@fortawesome/free-brands-svg-icons'

// Register only these icons we need
library.add(
    faCheck,
    faCheckCircle,
    faCheckSquare,
    faComments,
    faCreditCard,
    faPaypal,
    faUniversity,
    faDownload,
    faEnvelope,
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
