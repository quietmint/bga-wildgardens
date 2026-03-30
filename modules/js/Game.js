/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Wild Gardens implementation : © quietmint
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

/**
 * We create one State class per declared state on the PHP side, to handle all state specific code here.
 * onEnteringState, onLeavingState and onPlayerActivationChange are predefined names that will be called by the framework.
 * When executing code in this state, you can access the args using this.args
 */
class PlayerMove {
  constructor(game, bga) {
    this.game = game;
    this.bga = bga;
    this.cleanup = [];
  }

  onEnteringState(args, isCurrentPlayerActive) {
    if (isCurrentPlayerActive) {
      const player = this.game.gamedatas.players[this.bga.players.getActivePlayerId()];
      Object.keys(args.possible).forEach((space) => {
        const el = document.getElementById("wg-space" + space);
        el.classList.add("wg-space-possible");
        el.style.borderColor = "#" + player.color;
        const listener = () => this.onClick(args, space);
        el.addEventListener("click", listener);
        this.cleanup.push(() => el.removeEventListener("click", listener));
      });
    }
  }

  onLeavingState(args, isCurrentPlayerActive) {
    for (let el of document.querySelectorAll("#wg-path .wg-space-possible")) {
      el.classList.remove("wg-space-possible");
      el.style = null;
    }
    for (let cleanup of this.cleanup) {
      cleanup();
    }
  }

  onClick(args, space) {
    const distances = args.possible[space];
    if (distances.length > 1) {
      this.bga.statusBar.removeActionButtons();
      for (let distance of distances) {
        this.bga.statusBar.addActionButton(_("Move ${distance}"), () => this.bga.actions.performAction("actMove", { space, distance }));
      }
      this.bga.statusBar.addActionButton(_("Cancel"), () => this.bga.statusBar.removeActionButtons(), { color: "secondary" });
    } else {
      this.bga.actions.performAction("actMove", { space, distance: distances[0] });
    }
  }
}

class PlayerSkills {
  constructor(game, bga) {
    this.game = game;
    this.bga = bga;
    this.cleanup = [];
  }

  onEnteringState(args, isCurrentPlayerActive) {
    if (isCurrentPlayerActive) {
      this.bga.statusBar.addActionButton(_("End Turn"), () => this.bga.actions.performAction("actEndTurn", {}), { color: "secondary" });
      const player = this.game.gamedatas.players[this.bga.players.getActivePlayerId()];
      if (args.forages && args.forages.length > 0) {
        for (let forage of args.forages) {
          const el = document.getElementById("wg-forage" + forage);
          el.classList.add("wg-forage-possible");
          el.style.borderColor = "#" + player.color;
          const listener = () => this.onClickForage(args, forage);
          el.addEventListener("click", listener);
          this.cleanup.push(() => el.removeEventListener("click", listener));
        }
      }
    }
  }

  onLeavingState(args, isCurrentPlayerActive) {
    for (let el of document.querySelectorAll("#wg-path .wg-forage-possible")) {
      el.classList.remove("wg-forage-possible");
      el.style = null;
    }
    for (let cleanup of this.cleanup) {
      cleanup();
    }
  }

  onClickForage(args, forage) {
    console.log("onClickForage", args, forage);
    this.bga.actions.performAction("actForage", { forage });
  }
}

export class Game {
  constructor(bga) {
    console.log("wildgardens constructor");
    this.bga = bga;

    // Declare the State classes
    this.bga.states.register("PlayerMove", new PlayerMove(this, bga));
    this.bga.states.register("PlayerSkills", new PlayerSkills(this, bga));

    // Uncomment the next line to show debug informations about state changes in the console. Remove before going to production!
    this.bga.states.logger = console.log;

    // Here, you can init the global variables of your user interface
    // Example:
    // this.myGlobalValue = 0;
  }

  setup(gamedatas) {
    this.gamedatas = gamedatas;
    console.log("Setup", gamedatas);

    this.renderPath();
    Object.values(gamedatas.players).forEach((player) => {
      this.renderPlayerInventory(player);
    });

    // Notifications
    this.bga.notifications.setupPromiseNotifications({
      logger: console.log,
    });
  }

  renderPath() {
    const path = this.gamedatas.path;
    let pathEl = document.getElementById("wg-path");
    if (pathEl == null) {
      let html = `<div id="wg-path" class="wg-${path.type}">`;
      // Forage tokens
      let i = 1;
      for (let forage of path.forage) {
        const title = this.getIngredientName(forage);
        html += `<div id="wg-forage${i}" class="wg-forage wg-${forage}" title="${title}">${i}</div>`;
        i++;
      }
      // Spaces
      for (i = 1; i <= path.spaces; i++) {
        html += `<div id="wg-space${i}" class="wg-space wg-space${i}">${i}</div>`;
      }
      // Players
      for (let player of Object.values(this.gamedatas.players)) {
        const avatar = this.bga.players.getPlayerAvatarUrl(player.id);
        html += `<div id="wg-player-${player.id}" class="wg-player wg-space${player.pathSpace}" style="background-color: #${player.color}; background-image: url(${avatar}); border-color: #${player.color};" title="${player.name}"></div>`;
      }
      html += `</div>`;
      this.bga.gameArea.getElement().insertAdjacentHTML("beforeend", html);
    } else {
      // Update path type
      pathEl.classList.remove("wg-spring", "wg-fall");
      pathEl.classList.add("wg-" + path.type);

      // Update forage tokens
      let i = 1;
      for (let forage of path.forage) {
        const forageEl = document.getElementById("wg-forage" + i);
        forageEl.classList.remove("wg-aquatic", "wg-bark", "wg-flower", "wg-fruit", "wg-fungus", "wg-leaf", "wg-nut", "wg-root");
        forageEl.classList.add("wg-" + forage);
        forageEl.title = this.getIngredientName(forage);
        i++;
      }

      // Add/remove space 14
      const space14El = document.getElementById("wg-space14");
      if (space14El == null && path.spaces == 14) {
        const space13El = document.getElementById("wg-space13");
        space13El.insertAdjacentHTML("afterend", '<div id="wg-space14" class="wg-space wg-space14">14</div>');
      } else if (space14El != null && path.spaces == 13) {
        space14El.remove();
      }
    }
  }

  renderPlayerInventory(player) {
    const ingredients = ["aquatic", "bark", "flower", "fruit", "fungus", "leaf", "nut", "root"];
    let invEl = document.getElementById(`wg-inventory-${player.id}`);
    if (invEl == null) {
      let html = `<div id="wg-inventory-${player.id}" class="wg-inventory">`;
      for (let ingredient of ingredients) {
        const title = this.getIngredientName(ingredient);
        html += `<div id="wg-inv-${ingredient}-${player.id}" class="wg-inv wg-${ingredient}" title="${title}"></div>`;
      }
      html += `</div>`;
      this.bga.playerPanels.getElement(player.id).insertAdjacentHTML("beforeend", html);
      for (let ingredient of ingredients) {
        const counter = new ebg.counter();
        counter.create(`wg-inv-${ingredient}-${player.id}`, { value: player[ingredient], playerCounter: ingredient, playerId: player.id });
      }
    }
  }

  ///////////////////////////////////////////////////
  //// Notifications

  async notif_move(args) {
    const playerEl = document.getElementById(`wg-player-${args.player_id}`);
    for (let i = 1; i <= this.gamedatas.path.spaces; i++) {
      playerEl.classList.remove("wg-space" + i);
    }
    playerEl.classList.add("wg-space" + args.space);
  }

  async notif_path(args) {
    this.gamedatas.path = args.path;
    this.renderPath();
  }

  async notif_setPlayerCounter(args) {
    const { name, value, oldValue, inc, absInc, playerId } = args;
  }

  ///////////////////////////////////////////////////
  //// Utility methods

  getIngredientName(ingredient) {
    switch (ingredient) {
      case "aquatic":
        return "Aquatic";
      case "bark":
        return "Bark/Stem";
      case "flower":
        return "Flower";
      case "fruit":
        return "Fruit";
      case "fungus":
        return "Fungus";
      case "leaf":
        return "Leaf";
      case "nut":
        return "Nut/Seed";
      case "root":
        return "Root";
      default:
        return null;
    }
  }
}
