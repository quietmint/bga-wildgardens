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

const BgaAnimations = await importEsmLib("bga-animations", "1.x");
const BgaCards = await importEsmLib("bga-cards", "1.x");

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
        const possible = args.possible[space];
        const spaceEl = document.getElementById("wg-space" + space);
        possible.distances.forEach((distance) => {
          const newDiv = document.createElement("div");
          newDiv.classList.add("wg-space-possible", "color-" + player.color);
          newDiv.insertAdjacentText("beforeend", distance == 0 ? "×" : distance);
          if (possible.picnic) {
            newDiv.insertAdjacentHTML("beforeend", '<div class="wg-picnic"></div>');
          }
          newDiv.addEventListener("click", () => this.onClick(space, distance));
          spaceEl.appendChild(newDiv);
        });
        this.cleanup.push(() => spaceEl.replaceChildren());
      });
    }
  }

  onLeavingState(args, isCurrentPlayerActive) {
    for (let cleanup of this.cleanup) {
      cleanup();
    }
  }

  onClick(space, distance) {
    this.bga.actions.performAction("actMove", { space, distance });
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
          el.classList.add("wg-forage-possible", "color-" + player.color);
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

    this.bga.states.register("PlayerMove", new PlayerMove(this, bga));
    this.bga.states.register("PlayerSkills", new PlayerSkills(this, bga));
    this.bga.states.logger = console.log;

    this.animationManager = new BgaAnimations.Manager({
      animationsActive: () => this.bga.gameui.bgaAnimationsActive(),
    });

    // create the card manager
    this.cardsManager = new BgaCards.Manager({
      animationManager: this.animationManager,
      type: "mygame-card",
      getId: (card) => card.id,
      setupFrontDiv: (card, div) => {
        div.style.background = "blue";
        this.bga.gameui.addTooltipHtml(div.id, `tooltip of ${card.type}`);
      },
    });
  }

  setup(gamedatas) {
    this.gamedatas = gamedatas;
    console.log("Setup", gamedatas);

    var playersList = Object.values(gamedatas.players);
    playersList.forEach((player) => {
      player.avatarUrl = this.bga.players.getPlayerAvatarUrl(player.id);
    });
    this.renderJarBoard();
    this.renderPath();
    this.renderRecipes();
    this.renderGuests();
    playersList.forEach((player) => {
      this.renderPlayerBoard(player);
      this.renderPlayerPanel(player);
    });

    // Notifications
    this.bga.notifications.setupPromiseNotifications({
      logger: console.log,
    });
  }

  renderJarBoard() {
    let jarboardEl = document.getElementById("bg-jarboard");
    if (jarboardEl == null) {
      let html = `<div class="wg-title">${_("Jar Scoring")}</div>`;
      html += `<div id="wg-jarboard">`;
      html += `<table><thead><tr><th><div class="wg-log-icon wg-jar"></div></th>`;
      for (let j of this.gamedatas.jarboard) {
        html += `<th>${j}<div class="wg-point-icon wg-star"></div></th>`;
      }
      html += `</tr></thead><tbody>`;
      for (let player of Object.values(this.gamedatas.players)) {
        html += `<tr><th class="wg-player-name color-${player.color}"><div id="wg-jarboard-${player.id}" class="wg-player color-${player.color}" style="background-image: url(${player.avatarUrl})" title="${player.name}"></div>${player.name}</th>`;
        for (let j of this.gamedatas.jarboard) {
          html += `<td id="wg-jarboard-${j}-${player.id}"></td>`;
        }
        html += `</tr>`;
      }
      html += `</tbody></table></div>`;
      this.bga.gameArea.getElement().insertAdjacentHTML("beforeend", html);
      for (let player of Object.values(this.gamedatas.players)) {
        const jpEl = document.getElementById("wg-jarboard-" + player.id);
        const destEl = document.getElementById("wg-jarboard-" + player.jarScore + "-" + player.id);
        this.animationManager.slideToElementAndAttach(jpEl, destEl, destEl);
      }
    }
  }

  renderPath() {
    const path = this.gamedatas.path;
    let pathEl = document.getElementById("wg-path");
    if (pathEl == null) {
      let html = `<div class="wg-title">${_("Path")}</div>`;
      html += `<div id="wg-path" class="wg-${path.type}">`;
      // Forage tokens
      let i = 1;
      for (let forage of path.forage) {
        const title = this.getIngredientName(forage);
        html += `<div id="wg-forage${i}" class="wg-forage wg-${forage}" title="${title}"></div>`;
        i++;
      }
      // Spaces
      for (i = 1; i <= path.spaces; i++) {
        html += `<div id="wg-space${i}" class="wg-space wg-space${i}"></div>`;
      }
      // Players
      for (let player of Object.values(this.gamedatas.players)) {
        html += `<div id="wg-player-${player.id}" class="wg-player color-${player.color} wg-space${player.pathSpace}" style="background-image: url(${player.avatarUrl})" title="${player.name}"></div>`;
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

  renderRecipes() {
    let recipesEl = document.getElementById("wg-recipes");
    if (recipesEl == null) {
      let html = `<div class="wg-title">${_("Recipes")}</div>`;
      html += `<div id="wg-recipes">`;
      html += `</div>`;
      this.bga.gameArea.getElement().insertAdjacentHTML("beforeend", html);
    }
  }

  renderGuests() {
    let guestsEl = document.getElementById("wg-guests");
    if (guestsEl == null) {
      let html = `<div class="wg-title">${_("Guests")}</div>`;
      html += `<div id="wg-guests">`;
      html += `</div>`;
      this.bga.gameArea.getElement().insertAdjacentHTML("beforeend", html);
    }
  }

  renderPlayerBoard(player) {
    let boardEl = document.getElementById(`wg-playerboard-${player.id}`);
    if (boardEl == null) {
      let html = `<div class="wg-title color-${player.color}">${player.name}</div>`;
      html += `<div id="wg-playerboard-${player.id}" class="wg-playerboard color-${player.color}">`;
      html += `</div>`;
      this.bga.gameArea.getElement().insertAdjacentHTML("beforeend", html);
    }
  }

  renderPlayerPanel(player) {
    let tokensEl = document.getElementById(`wg-tokens-${player.id}`);
    if (tokensEl == null) {
      const pointEl = document.getElementById(`icon_point_${player.id}`);
      if (pointEl != null) {
        pointEl.classList.remove("fa", "fa-star");
        pointEl.classList.add("wg-point-icon", "wg-star");
      }
      const ingredients = ["aquatic", "bark", "flower", "fruit", "fungus", "leaf", "nut", "root"];
      let html = `<div id="wg-inventory-${player.id}" class="wg-inventory">`;
      for (let ingredient of ingredients) {
        const title = this.getIngredientName(ingredient);
        html += `<div id="wg-inv-${ingredient}-${player.id}" class="wg-inv wg-${ingredient}" title="${title}"></div>`;
      }
      html += `</div><div id="wg-tokens-${player.id}" class="wg-tokens">`;
      html += `<div id="wg-jar-${player.id}" class="wg-jar"></div>`;
      for (let distance = 0; distance <= 4; distance++) {
        const cssClass = player["distance" + distance] == 1 ? "color-" + player.color : "color-disabled";
        html += `<div id="wg-dist-${distance}-${player.id}" class="wg-dist ${cssClass}">${distance == 0 ? "×" : distance}</div>`;
      }
      html += `</div>`;
      this.bga.playerPanels.getElement(player.id).insertAdjacentHTML("beforeend", html);

      for (let ingredient of ingredients) {
        const counter = new ebg.counter();
        counter.create(`wg-inv-${ingredient}-${player.id}`, { value: player[ingredient], playerCounter: ingredient, playerId: player.id });
      }
      const jarCounter = new ebg.counter();
      jarCounter.create(`wg-jar-${player.id}`, { value: player.jar, playerCounter: "jar", playerId: player.id });
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
    this.gamedatas.players[args.player_id]["distance" + args.distance] = 0;
    const distEl = document.getElementById(`wg-dist-${args.distance}-${args.player_id}`);
    distEl.classList.add("color-disabled");
  }

  async notif_path(args) {
    this.gamedatas.path = args.path;
    this.renderPath();
  }

  async notif_setPlayerCounter(args) {
    if (args.forage) {
      const gameEl = this.bga.gameArea.getElement();
      gameEl.insertAdjacentHTML("beforeend", `<div class="wg-inv-float wg-${args.name}"></div>`);
      const floatEl = gameEl.lastElementChild;
      const sourceEl = document.getElementById(`wg-forage${args.forage}`);
      const destEl = document.getElementById(`wg-inv-${args.name}-${args.playerId}`);
      await this.animationManager.slideFloatingElement(floatEl, sourceEl, destEl, { easing: "ease-out" });
    }
  }

  ///////////////////////////////////////////////////
  //// Utility methods

  bgaFormatText(log, args) {
    try {
      if (log && args && !args.processed) {
        args.processed = true;

        if (args.distanceName) {
          const player = this.gamedatas.players[args.player_id];
          args.distanceName = `<div class="wg-log-icon wg-dist color-${player.color}">${args.distanceName}</div>`;
        }
        if (args.ingredient) {
          args.ingredient = `<b>${args.ingredient}</b>`;
        }
        if (args.icon) {
          args.icon = `<div class="wg-log-icon wg-${args.icon}"></div>`;
        }

        // list of special keys we want to replace with images
        const keys = ["place_name", "token_name"];

        for (let i in keys) {
          const key = keys[i];
          if (args[key]) args[key] = this.getTokenDiv(key, args);
        }
      }
    } catch (e) {
      console.error(log, args, "Exception thrown", e.stack);
    }
    return { log, args };
  }

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
