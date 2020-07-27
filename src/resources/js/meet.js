/**
 * Application code for the Meet UI
 */

import routes from './routes-meet.js'

window.routes = routes
window.isAdmin = false

require('./app')

// Register additional icons
import { library } from '@fortawesome/fontawesome-svg-core'

import {
    faAlignLeft,
    faCompress,
    faDesktop,
    faExpand,
    faMicrophone,
    faPowerOff,
    faVideo,
    faVolumeMute
} from '@fortawesome/free-solid-svg-icons'

// Register only these icons we need
library.add(
    faAlignLeft,
    faCompress,
    faDesktop,
    faExpand,
    faMicrophone,
    faPowerOff,
    faVideo,
    faVolumeMute
)
