<?php

namespace IBRExplorer\Entity\Enum\PcapFile;

enum PcapFileStatus: int {

    case WaitingUpload = 1;
    case Uploaded = 2;
    case WaitingProcess = 3;
    case Processing = 4;
    case Error = 5;
    case Processed = 6;

}
