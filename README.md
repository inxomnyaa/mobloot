# mobloot 
Implements vanilla loot tables via virion. If wanted, it can fully replace PM loot. Needs addons to work
## Usage
- You need to have a vanilla.zip behaviour pack installed in your server (does not need to force resources). You can get it by extracting your game files. It is also possible to make a custom pack that only includes the loot tables to safe on file size.
- `xenialdan\mobloot\API::init()` must be called in the plugin to properly make this work
- If you want PocketMine's loot to be replaced with vanilla loot, you must use `xenialdan\mobloot\listener::register(Plugin $plugin)`
## Information
- Notice: i am doing this project in my spare time. I can not completely keep up with the changes Mojang introduces to loot tables, this is why some functionality is missing.
- PocketMine does not yet have every single API method to reproduce every behaviour (enchanting, crafting modifiers, entity methods), so those are likely missing from the virion