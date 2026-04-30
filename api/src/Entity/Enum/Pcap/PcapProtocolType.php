<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Enum\Pcap;

enum PcapProtocolType: int {

    case Hopopt = 0;
    case Icmp = 1;
    case Igmp = 2;
    case Ggp = 3;
    case Ipv4 = 4;
    case St = 5;
    case Tcp = 6;
    case Cbt = 7;
    case Egp = 8;
    case Igp = 9;
    case BbnRccMon = 10;
    case NvpIi = 11;
    case Pup = 12;
    case Argus = 13;
    case Emcon = 14;
    case Xnet = 15;
    case Chaos = 16;
    case Udp = 17;
    case Mux = 18;
    case DcnMeas = 19;
    case Hmp = 20;
    case Prm = 21;
    case XnsIdp = 22;
    case Trunk1 = 23;
    case Trunk2 = 24;
    case Leaf1 = 25;
    case Leaf2 = 26;
    case Rdp = 27;
    case Irtp = 28;
    case IsoTp4 = 29;
    case Netblt = 30;
    case MfeNsp = 31;
    case MeritInp = 32;
    case Dccp = 33;
    case ThreePc = 34;
    case Idpr = 35;
    case Xtp = 36;
    case Ddp = 37;
    case IdprCmtp = 38;
    case Tpp = 39;
    case Il = 40;
    case Ipv6 = 41;
    case Sdrp = 42;
    case Ipv6Route = 43;
    case Ipv6Frag = 44;
    case Idrp = 45;
    case Rsvp = 46;
    case Gre = 47;
    case Dsr = 48;
    case Bna = 49;
    case Esp = 50;
    case Ah = 51;
    case Inlsp = 52;
    case Swipe = 53;
    case Narp = 54;
    case Mobile = 55;
    case Tlsp = 56;
    case Skip = 57;
    case Icmpv6 = 58;
    case Ipv6NoNxt = 59;
    case Ipv6Opts = 60;
    case Other = 255;

}
