# MultiVersion v1.0
This plugin lets you allow versions such as 1.16.210 on the PocketMine API Version, 3.17.0.
Original plugin from https://github.com/ethaniccc/HackyNewVersionSupport , Fixed by the Astral Server Development Team.

## Information 
⚠ **This plugin is currently Work In Progress.**

In the future, we will possibly find a fix for supporting all minecraft versions above 1.16x. This plugin currently only works on PocketMine API 3.17.0 and supports the versions
1.16.100, 1.16.200, 1.16.201 & 1.16.210.

**Known Bugs**
- Hits do not register.
- On skin change, Players on the 1.16.210 client get crashed.

**Fix those Bugs**

Hit Registration - Try enabling xbox-auth and deleting the multiworld plugin, This may fix it.
On Skin Change - Make a plugin that cancels the PlayerChangeSkinEvent

## Installation

 **Hard way (developer friendly):**

- Download the SRC.
- Install the [PocketMine-Devtools](https://poggit.pmmp.io/p/DevTools/1.14.2) plugin.
- Create a folder named "MultiVersion" in your plugins folder.
- Drag and drop the files you just downloaded!

**Easy way (normal user friendly):**
- Download the .PHAR from the [releases](https://github.com/AstralClientNet/MultiVersion-Plugin/releases/) section
- Drop the plugin into your plugins folder!

## Contributing

- You can freely contribute to this plugin and help us out! If we find something that we might not need in the code YOU add, We may not accept it.
