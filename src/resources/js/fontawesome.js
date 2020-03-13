
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
//import { } from '@fortawesome/free-regular-svg-icons'
//import { } from '@fortawesome/free-brands-svg-icons'
import {
    faCheck,
    faGlobe,
    faInfoCircle,
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
    faGlobe,
    faInfoCircle,
    faSyncAlt,
    faTrashAlt,
    faUser,
    faUserCog,
    faUsers,
    faWallet
)

export default FontAwesomeIcon
